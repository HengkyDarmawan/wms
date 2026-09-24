<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Receipt\Models\VendorReturnLine;
use App\Domain\Stock\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `vendor_return.create` — RTV `submitted` (Katalog §2.16).
 *
 * Baris hanya dari baris GRN vendor yang masih di bin Karantina dengan hasil
 * QC `rejected` (atau `quarantined` — "alasan lain"), BR-GRN-04. Jumlahnya
 * tidak boleh melebihi yang belum dimuat RTV lain yang masih berjalan.
 *
 * RTV langsung diteruskan ke `pending_approval` dan diputus lewat mesin
 * approval (20-approval §13): approver ditentukan aturan RTV, bukan role;
 * tanpa aturan RTV langsung disetujui (A-08, A-93).
 */
class CreateVendorReturn
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly ApprovalEngine $approval,
    ) {}

    /**
     * @param  array<int, array{goods_receipt_line_id: int|string, qty_base: float|string, reason_code_id?: int|string|null}>  $lines
     */
    public function handle(GoodsReceipt $receipt, array $lines, ?string $notes = null, ?User $actor = null): VendorReturn
    {
        if ($receipt->receipt_type !== ReceiptType::Vendor) {
            throw ReceiptRuleException::rule('BR-GRN-04', 'Retur ke vendor hanya untuk penerimaan dari vendor.');
        }

        if (! in_array($receipt->status, [GoodsReceiptStatus::Received, GoodsReceiptStatus::Completed], true)) {
            throw ReceiptRuleException::rule('BR-GRN-04', 'GRN belum diterima; belum ada barang yang bisa diretur.');
        }

        $milik = $receipt->lines()->with('item', 'receivingBin')->get()->keyBy('id');
        $baris = [];

        foreach ($lines as $isi) {
            $qty = round((float) ($isi['qty_base'] ?? 0), 4);

            if ($qty <= 0) {
                continue;
            }

            /** @var GoodsReceiptLine|null $l */
            $l = $milik->get((int) ($isi['goods_receipt_line_id'] ?? 0));

            if ($l === null) {
                throw ReceiptRuleException::rule('BR-GRN-04', 'Ada baris retur yang bukan milik GRN ini.');
            }

            if (! $l->receivingBinIsQuarantine()
                || ! in_array($l->qc_result, [QcResult::Rejected, QcResult::Quarantined], true)) {
                throw ReceiptRuleException::rule(
                    'BR-GRN-04',
                    'Baris '.$l->item->code.' tidak di Karantina dengan hasil QC Ditolak atau Karantina; tidak bisa diretur.',
                );
            }

            $sisa = (float) $l->qty_received - $this->sudahDiretur($l);

            if ($qty - $sisa > 0.00005) {
                throw ReceiptRuleException::field(
                    'BR-GRN-04',
                    'qty_base',
                    'Baris '.$l->item->code.': retur '.$qty.' melebihi sisa yang bisa diretur ('.$sisa.').',
                );
            }

            $alasan = (int) ($isi['reason_code_id'] ?? 0) ?: (int) ($l->qc_reason_id ?? 0);

            if ($alasan === 0 || ! ReasonCode::query()->whereKey($alasan)->where('context', ReasonContext::Reject->value)->exists()) {
                throw ReceiptRuleException::field('BR-GEN-11', 'reason_code_id', 'Baris '.$l->item->code.': alasan retur wajib dipilih.');
            }

            $baris[] = [
                'goods_receipt_line_id' => $l->id,
                'item_id' => $l->item_id,
                'lot_id' => $l->lot_id,
                'serial_id' => $l->serial_id,
                'piece_id' => $l->piece_id,
                'bin_id' => $l->receiving_bin_id,
                'stock_status' => $l->quarantineStockStatus(),
                'qty_base' => $qty,
                'reason_code_id' => $alasan,
            ];
        }

        if ($baris === []) {
            throw ReceiptRuleException::rule('BR-GRN-04', 'Pilih minimal satu baris dengan jumlah retur.');
        }

        $receipt->loadMissing('warehouse');

        return DB::transaction(function () use ($receipt, $baris, $notes, $actor) {
            $rtv = VendorReturn::create([
                'number' => $this->nomor->next('RTV', (string) $receipt->warehouse->code),
                'warehouse_id' => $receipt->warehouse_id,
                'vendor_id' => $receipt->vendor_id,
                'goods_receipt_id' => $receipt->id,
                'status' => VendorReturnStatus::Submitted,
                'submitted_by' => $actor?->id,
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            ]);

            foreach ($baris as $b) {
                VendorReturnLine::create($b + ['vendor_return_id' => $rtv->id]);
            }

            activity('receipt')->performedOn($rtv)->causedBy($actor)
                ->withProperties(['grn' => $receipt->number, 'baris' => count($baris)])
                ->log('RTV diajukan');

            // Katalog §2.16: submitted → pending_approval otomatis, lalu mesin
            // approval membuat snapshot aturan; tanpa aturan langsung approved
            // (A-08; menggantikan jalur sementara A-80, lihat A-93).
            $rtv->forceFill(['status' => VendorReturnStatus::PendingApproval])->save();

            $this->approval->submit(ApprovalDocumentType::VendorReturn, $rtv, $actor);

            return $rtv->refresh();
        });
    }

    private function sudahDiretur(GoodsReceiptLine $line): float
    {
        return (float) VendorReturnLine::query()
            ->where('goods_receipt_line_id', $line->id)
            ->whereHas('vendorReturn', fn ($q) => $q->whereNotIn('status', [
                VendorReturnStatus::Rejected->value,
                VendorReturnStatus::Cancelled->value,
            ]))
            ->sum('qty_base');
    }
}
