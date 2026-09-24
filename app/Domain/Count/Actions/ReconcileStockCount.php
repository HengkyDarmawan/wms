<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustmentLine;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `count.reconcile` — `in_progress`/`recount` → `reconciling`
 * (Katalog §2.13) dan pengajuan hasil ke approval tingkat sesi.
 *
 * Guard: semua bin terhitung (termasuk hitung ulang) dan setiap selisih
 * **besar** punya akar masalah (BR-OPN-07). Efek: draf ADJ per gudang yang
 * punya selisih (BR-OPN-06; berstatus `submitted`, approval-nya dilewati)
 * — kecuali pemeriksaan mendadak (BR-OPN-10) — lalu sesi masuk mesin
 * approval (BR-OPN-09).
 *
 * Setelah ditolak approver sesi tetap `reconciling`; memanggil aksi ini lagi
 * mengajukan ulang. Draf ADJ lama dibatalkan dan dibuat ulang dari angka
 * terbaru (A-97).
 */
class ReconcileStockCount
{
    public function __construct(
        private readonly ApprovalEngine $approval,
        private readonly DocumentNumber $nomor,
    ) {}

    public function handle(StockCount $count, ?User $actor = null): StockCount
    {
        $count = StockCount::withoutGlobalScopes()->findOrFail($count->id);

        $ajukanUlang = $count->status === StockCountStatus::Reconciling;

        if (! $count->status->isCounting() && ! $ajukanUlang) {
            throw CountRuleException::rule('BR-GEN-01', 'Sesi berstatus '.$count->status->label().' tidak bisa direkonsiliasi.');
        }

        if ($ajukanUlang && $this->approval->pendingSnapshot(ApprovalDocumentType::StockCount, (int) $count->id) !== null) {
            throw CountRuleException::rule('BR-APR-01', 'Hasil opname sedang menunggu approval.');
        }

        if (CountAssignment::query()->where('stock_count_id', $count->id)->pending()->exists()
            || CountLine::query()->where('stock_count_id', $count->id)->whereNull('final_qty')->exists()) {
            throw CountRuleException::rule('BR-OPN-05', 'Semua bin harus selesai dihitung (termasuk hitung ulang) sebelum rekonsiliasi.');
        }

        $tanpaAkar = CountLine::query()->with('item:id,code', 'bin:id,code')
            ->where('stock_count_id', $count->id)
            ->where('variance_class', VarianceClass::Major->value)
            ->whereNull('root_cause')->first();

        if ($tanpaAkar !== null) {
            throw CountRuleException::rule(
                'BR-OPN-07',
                'Selisih besar '.$tanpaAkar->item?->code.' di bin '.$tanpaAkar->bin?->code.' wajib diberi kategori akar masalah.',
            );
        }

        return DB::transaction(function () use ($count, $actor, $ajukanUlang) {
            if ($count->postsAdjustments()) {
                $this->drafAdj($count, $actor);
            }

            $count->forceFill([
                'status' => StockCountStatus::Reconciling,
                'submitted_by' => $actor?->id,
                'reconciled_at' => now(),
                'reject_reason_id' => null,
            ])->save();

            activity('count')->performedOn($count)->causedBy($actor)
                ->log($ajukanUlang ? 'Rekonsiliasi diajukan ulang ke approval' : 'Rekonsiliasi: hasil opname diajukan ke approval');

            $this->approval->submit(ApprovalDocumentType::StockCount, $count, $actor);

            return $count->refresh();
        });
    }

    /** BR-OPN-06: satu ADJ per gudang yang punya selisih. */
    private function drafAdj(StockCount $count, ?User $actor): void
    {
        $lama = StockAdjustment::withoutGlobalScopes()->where('stock_count_id', $count->id)
            ->where('status', StockAdjustmentStatus::Submitted->value)->get();

        foreach ($lama as $adj) {
            $adj->forceFill([
                'status' => StockAdjustmentStatus::Cancelled,
                'cancel_reason_id' => ReasonCode::query()->where('context', ReasonContext::Cancel->value)
                    ->where('code', 'WRONG_INPUT')->value('id'),
                'notes' => 'Diganti rekonsiliasi ulang '.$count->number,
            ])->save();

            activity('adjustment')->performedOn($adj)->causedBy($actor)->log('Draf ADJ opname diganti rekonsiliasi ulang');
        }

        DB::table('stock_count_warehouses')->where('stock_count_id', $count->id)->update(['stock_adjustment_id' => null]);

        $alasan = ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->where('code', 'COUNT_FIX')->value('id')
            ?? ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->value('id');

        if ($alasan === null) {
            throw CountRuleException::rule('BR-GEN-02', 'Master Alasan konteks penyesuaian masih kosong.');
        }

        $selisih = CountLine::query()->with('bin:id,warehouse_id')
            ->where('stock_count_id', $count->id)->whereNotNull('variance_class')
            ->orderBy('bin_id')->orderBy('id')->get()
            ->filter(fn (CountLine $l) => $l->hasVariance())
            ->groupBy(fn (CountLine $l) => (int) $l->bin->warehouse_id);

        foreach ($selisih as $gudangId => $baris) {
            $gudang = Warehouse::withoutGlobalScopes()->findOrFail($gudangId);

            $adj = StockAdjustment::create([
                'number' => $this->nomor->next('ADJ', (string) $gudang->code),
                'warehouse_id' => $gudang->id,
                'origin' => AdjustmentOrigin::Count,
                'stock_count_id' => $count->id,
                'reason_code_id' => $alasan,
                'status' => StockAdjustmentStatus::Submitted,
                'submitted_by' => $actor?->id,
                'notes' => 'Hasil opname '.$count->number,
            ]);

            foreach ($baris as $l) {
                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $adj->id,
                    'item_id' => $l->item_id,
                    'bin_id' => $l->bin_id,
                    'lot_id' => $l->lot_id,
                    'serial_id' => $l->serial_id,
                    'piece_id' => $l->piece_id,
                    'qty_delta' => (float) $l->variance_qty,
                    'stock_status' => $l->stock_status,
                    'count_line_id' => $l->id,
                    'notes' => $l->root_cause !== null ? 'Akar masalah: '.$l->root_cause->label() : null,
                ]);
            }

            DB::table('stock_count_warehouses')->where('stock_count_id', $count->id)
                ->where('warehouse_id', $gudang->id)->update(['stock_adjustment_id' => $adj->id]);

            activity('adjustment')->performedOn($adj)->causedBy($actor)
                ->withProperties(['opname' => $count->number, 'baris' => $baris->count()])
                ->log('Draf ADJ dari rekonsiliasi opname (approval di tingkat sesi)');
        }
    }
}
