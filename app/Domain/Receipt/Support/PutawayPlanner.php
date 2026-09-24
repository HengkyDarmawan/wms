<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Models\PutawayTaskLine;
use App\Domain\Stock\Support\DocumentNumber;
use Illuminate\Support\Collection;

/**
 * Membuat tugas put-away dari baris GRN yang boleh ditaruh (Katalog §2.6).
 *
 * PUT lahir otomatis — saat GRN `completed`, atau saat baris yang tadinya
 * dikarantina akhirnya lolos QC. Baris yang sudah punya PUT menunggu atau
 * selesai tidak dibuatkan lagi, jadi memanggilnya dua kali aman.
 */
class PutawayPlanner
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly PutawaySuggester $saran,
        private readonly ReceiptBins $bins,
    ) {}

    /**
     * @param  Collection<int, GoodsReceiptLine>|null  $lines  kosong = semua baris GRN
     */
    public function planFor(GoodsReceipt $receipt, ?Collection $lines = null, ?User $actor = null): ?PutawayTask
    {
        $receipt->loadMissing('warehouse');

        $kandidat = ($lines ?? $receipt->lines()->with('item.category', 'receivingBin')->get())
            ->filter(fn (GoodsReceiptLine $l) => $l->isPutawayEligible() && ! $this->sudahDirencanakan($l))
            ->values();

        if ($kandidat->isEmpty()) {
            return null;
        }

        $penerimaan = $this->bins->receiving($receipt->warehouse);

        $tugas = PutawayTask::create([
            'number' => $this->nomor->next('PUT', (string) $receipt->warehouse->code),
            'goods_receipt_id' => $receipt->id,
            'warehouse_id' => $receipt->warehouse_id,
            'status' => PutawayTaskStatus::Pending,
        ]);

        /** @var array<int, float> $rencana */
        $rencana = [];

        foreach ($kandidat as $l) {
            $qty = (float) $l->qty_received;
            $bin = $this->saran->suggest($l->item, $receipt->warehouse, $qty, $rencana);

            if ($bin !== null) {
                $rencana[$bin->id] = ($rencana[$bin->id] ?? 0) + $qty;
            }

            PutawayTaskLine::create([
                'putaway_task_id' => $tugas->id,
                'goods_receipt_line_id' => $l->id,
                'item_id' => $l->item_id,
                'lot_id' => $l->lot_id,
                'serial_id' => $l->serial_id,
                'piece_id' => $l->piece_id,
                // Lolos QC berarti sudah dipindah dari Karantina ke Penerimaan.
                'from_bin_id' => $l->qc_result === QcResult::Passed ? $penerimaan->id : $l->receiving_bin_id,
                'suggested_bin_id' => $bin?->id,
                'qty_base' => $qty,
            ]);
        }

        activity('receipt')
            ->performedOn($tugas)
            ->causedBy($actor)
            ->withProperties(['grn' => $receipt->number, 'baris' => $kandidat->count()])
            ->log('Tugas put-away dibuat');

        return $tugas;
    }

    private function sudahDirencanakan(GoodsReceiptLine $line): bool
    {
        return PutawayTaskLine::query()
            ->where('goods_receipt_line_id', $line->id)
            ->whereHas('task', fn ($q) => $q->withoutGlobalScopes()->where('status', '!=', PutawayTaskStatus::Cancelled->value))
            ->exists();
    }
}
