<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Support\PutawayPlanner;
use App\Domain\Receipt\Support\ReceiptBins;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `receipt.qc` — mencatat hasil QC satu baris GRN (BR-GRN-02, A-34).
 *
 * QC adalah **langkah**, bukan status: GRN tetap `received` (atau `completed`
 * untuk baris yang dikarantina lalu diputus ulang). Efeknya pada stok (A-78):
 *
 * - `passed` — Karantina QC (Karantina) → bin Penerimaan (Tersedia), siap
 *   di-put-away;
 * - `quarantined` — tidak bergerak; menunggu keputusan ulang;
 * - `rejected` — tetap di bin Karantina, kondisinya berubah menjadi Rusak;
 *   menunggu RTV (BR-GRN-04). Alasan `*` wajib (BR-GEN-11).
 *
 * Tidak ada kejadian stok: perpindahan di dalam gudang, sama seperti picking
 * ke Loading Area (matriks §14).
 */
class RecordQcResult
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly ReceiptBins $bins,
        private readonly PutawayPlanner $perencana,
    ) {}

    public function handle(
        GoodsReceiptLine $line,
        QcResult $result,
        ?int $reasonCodeId = null,
        ?string $note = null,
        ?User $actor = null,
    ): GoodsReceiptLine {
        $receipt = $line->receipt()->with('warehouse')->firstOrFail();

        if (! in_array($receipt->status, [GoodsReceiptStatus::Received, GoodsReceiptStatus::Completed], true)) {
            throw ReceiptRuleException::rule('BR-GRN-02', 'QC hanya untuk GRN yang barangnya sudah diterima.');
        }

        if (! $line->awaitsQc()) {
            throw ReceiptRuleException::rule(
                'BR-GRN-02',
                'Baris '.$line->item?->code.' tidak menunggu QC: tidak masuk Karantina, atau hasilnya sudah final.',
            );
        }

        // Barang yang sudah dimuat RTV berjalan tidak boleh diputus ulang: RTV
        // itu akan mengambilnya dari Karantina (BR-GRN-04).
        $dimuatRtv = $line->vendorReturnLines()
            ->whereHas('vendorReturn', fn ($q) => $q->whereNotIn('status', [
                VendorReturnStatus::Rejected->value,
                VendorReturnStatus::Cancelled->value,
            ]))
            ->exists();

        if ($dimuatRtv) {
            throw ReceiptRuleException::rule('BR-GRN-04', 'Baris '.$line->item?->code.' sedang dimuat retur ke vendor; batalkan RTV-nya dulu.');
        }

        if ($result === QcResult::Rejected) {
            $valid = $reasonCodeId !== null && ReasonCode::query()
                ->whereKey($reasonCodeId)
                ->where('context', ReasonContext::Reject->value)
                ->exists();

            if (! $valid) {
                throw ReceiptRuleException::field('BR-GEN-11', 'qc_reason', 'Alasan penolakan QC wajib dipilih.');
            }
        }

        return DB::transaction(function () use ($line, $receipt, $result, $reasonCodeId, $note, $actor) {
            $karantina = (int) $line->receiving_bin_id;

            match ($result) {
                QcResult::Passed => $this->posting($line, $receipt->number, (int) $receipt->id, $karantina,
                    (int) $this->bins->receiving($receipt->warehouse)->id, StockStatus::Available, $actor, 'QC lolos'),
                QcResult::Rejected => $this->posting($line, $receipt->number, (int) $receipt->id, $karantina,
                    $karantina, StockStatus::Damaged, $actor, 'QC ditolak', $reasonCodeId),
                QcResult::Quarantined => null,
            };

            $line->forceFill([
                'qc_result' => $result,
                'qc_by' => $actor?->id,
                'qc_at' => now(),
                'qc_reason_id' => $reasonCodeId,
                'qc_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ])->save();

            // Baris yang lolos setelah GRN selesai langsung dibuatkan PUT (Katalog §2.6).
            if ($result === QcResult::Passed && $receipt->status === GoodsReceiptStatus::Completed) {
                $this->perencana->planFor($receipt, collect([$line->refresh()]), $actor);
            }

            activity('receipt')
                ->performedOn($receipt)
                ->causedBy($actor)
                ->withProperties(['baris' => $line->id, 'item' => $line->item?->code, 'hasil' => $result->value])
                ->log('Hasil QC dicatat: '.$result->label());

            return $line->refresh();
        });
    }

    private function posting(
        GoodsReceiptLine $line,
        string $number,
        int $receiptId,
        int $fromBinId,
        int $toBinId,
        StockStatus $to,
        ?User $actor,
        string $notes,
        ?int $reasonCodeId = null,
    ): void {
        try {
            $this->ledger->post(new MovementRequest(
                item: $line->item,
                qtyBase: (float) $line->qty_received,
                fromBinId: $fromBinId,
                toBinId: $toBinId,
                stockStatus: $to,
                fromStockStatus: StockStatus::Quarantine,
                lotId: $line->lot_id,
                serialId: $line->serial_id,
                pieceId: $line->piece_id,
                documentType: 'goods_receipt',
                documentId: $receiptId,
                documentLineId: (int) $line->id,
                documentNumber: $number,
                reasonCodeId: $reasonCodeId,
                performedBy: $actor,
                notes: $notes,
            ));
        } catch (LedgerException $e) {
            throw ReceiptRuleException::rule($e->rule, 'QC baris '.$line->item?->code.' gagal: '.$e->getMessage());
        }
    }
}
