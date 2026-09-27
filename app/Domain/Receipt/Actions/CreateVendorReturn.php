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
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `vendor_return.create` — RTV `submitted` (Katalog §2.16).
 *
 * Dua sumber per baris GRN vendor (BR-GRN-04, A-290):
 *
 * - bagian **Rusak** yang dicatat saat GRN (`qty_damaged`, bin Karantina
 *   berkondisi Rusak) — tanpa QC, `part = damage`;
 * - bagian hasil **QC** lama: masih di bin Karantina dengan hasil `rejected`
 *   (atau `quarantined` — "alasan lain"), `part = qc`.
 *
 * Jumlah tiap bagian tidak boleh melebihi yang belum dimuat RTV lain yang
 * masih berjalan; kedua bagian dihitung terpisah.
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
     * @param  array<int, array{goods_receipt_line_id: int|string, qty_base: float|string, reason_code_id?: int|string|null, part?: string}>  $lines
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
        $dipakai = [];
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

            $bisaQc = $l->receivingBinIsQuarantine()
                && in_array($l->qc_result, [QcResult::Rejected, QcResult::Quarantined], true);
            $bisaRusak = (float) $l->qty_damaged > 0 && $l->damaged_bin_id !== null;
            $bagian = ($isi['part'] ?? null) === 'damage' || (($isi['part'] ?? null) === null && ! $bisaQc) ? 'damage' : 'qc';

            if (($bagian === 'qc' && ! $bisaQc) || ($bagian === 'damage' && ! $bisaRusak)) {
                throw ReceiptRuleException::rule(
                    'BR-GRN-04',
                    'Baris '.$l->item->code.' tidak punya barang rusak atau hasil QC Ditolak/Karantina di bin Karantina; tidak bisa diretur.',
                );
            }

            $rusak = $bagian === 'damage';
            $kunci = $l->id.'|'.$bagian;
            $dipakai[$kunci] = round(($dipakai[$kunci] ?? 0) + $qty, 4);
            $jatah = $rusak ? (float) $l->qty_damaged : (float) $l->qty_received;
            $sisa = round($jatah - $this->sudahDiretur($l, $rusak), 4);

            if ($dipakai[$kunci] - $sisa > 0.00005) {
                throw ReceiptRuleException::field(
                    'BR-GRN-04',
                    'qty_base',
                    'Baris '.$l->item->code.': retur '.$dipakai[$kunci].' melebihi sisa '.($rusak ? 'barang rusak ' : '').'yang bisa diretur ('.$sisa.').',
                );
            }

            $alasan = (int) ($isi['reason_code_id'] ?? 0) ?: (int) (($rusak ? $l->damage_reason_id : $l->qc_reason_id) ?? 0);
            $konteks = $rusak ? [ReasonContext::Reject->value, ReasonContext::Damage->value] : [ReasonContext::Reject->value];

            if ($alasan === 0 || ! ReasonCode::query()->whereKey($alasan)->whereIn('context', $konteks)->exists()) {
                throw ReceiptRuleException::field('BR-GEN-11', 'reason_code_id', 'Baris '.$l->item->code.': alasan retur wajib dipilih.');
            }

            $baris[] = [
                'goods_receipt_line_id' => $l->id,
                'item_id' => $l->item_id,
                'lot_id' => $l->lot_id,
                'serial_id' => $l->serial_id,
                'piece_id' => $l->piece_id,
                'bin_id' => $rusak ? $l->damaged_bin_id : $l->receiving_bin_id,
                'stock_status' => $rusak ? StockStatus::Damaged : $l->quarantineStockStatus(),
                'is_receipt_damage' => $rusak,
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

    private function sudahDiretur(GoodsReceiptLine $line, bool $rusak): float
    {
        return (float) VendorReturnLine::query()
            ->where('goods_receipt_line_id', $line->id)
            ->where('is_receipt_damage', $rusak)
            ->whereHas('vendorReturn', fn ($q) => $q->whereNotIn('status', [
                VendorReturnStatus::Rejected->value,
                VendorReturnStatus::Cancelled->value,
            ]))
            ->sum('qty_base');
    }
}
