<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Support\AdjustmentPoster;
use App\Domain\Count\Enums\CountType;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Stock\Actions\LockStockPeriod;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Bin;

/**
 * Akibat keputusan akhir approval sesi (Katalog §2.13):
 * `reconciling → approved` lalu otomatis `approved → closed`.
 *
 * 1. Bin cakupan dibuka lebih dulu — buku besar menolak bin beku (BR-OPN-02).
 * 2. ADJ per gudang `submitted → approved → posted` tanpa approval sendiri
 *    (BR-OPN-06, A-09); pemeriksaan mendadak tidak memposting (BR-OPN-10).
 * 3. Penanda hitung (A-67): dilepas untuk bin yang sudah dihitung; pada
 *    pemeriksaan mendadak justru dipasang di bin berselisih besar supaya
 *    sesi opname berikutnya mendahulukannya (A-101).
 * 4. Sesi bulanan yang ditutup memajukan tanggal kunci stok ke sehari
 *    sebelum snapshot (BR-STK-15, A-101).
 *
 * Laporan PDF dibuat saat diminta dari layar (A-101).
 */
class StockCountCloser
{
    public function __construct(
        private readonly ChangeBinStatus $binStatus,
        private readonly AdjustmentPoster $poster,
        private readonly LockStockPeriod $lock,
    ) {}

    public function approveAndClose(StockCount $count, ?User $actor): StockCount
    {
        $count = StockCount::withoutGlobalScopes()->lockForUpdate()->findOrFail($count->id);

        if ($count->status !== StockCountStatus::Reconciling) {
            throw CountRuleException::rule('BR-GEN-01', 'Hanya sesi berstatus Rekonsiliasi yang bisa disetujui.');
        }

        $count->forceFill([
            'status' => StockCountStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        activity('count')->performedOn($count)->causedBy($actor)->log('Sesi opname disetujui');

        foreach (Bin::withoutGlobalScopes()->where('frozen_by_count_id', $count->id)
            ->where('bin_status', BinStatus::Frozen->value)->get() as $bin) {
            $this->binStatus->unfreeze($bin, $actor);
        }

        if ($count->postsAdjustments()) {
            $adjs = StockAdjustment::withoutGlobalScopes()->where('stock_count_id', $count->id)
                ->where('status', StockAdjustmentStatus::Submitted->value)->orderBy('id')->get();

            foreach ($adjs as $adj) {
                $adj->forceFill([
                    'status' => StockAdjustmentStatus::Approved,
                    'approved_by' => $actor?->id,
                    'approved_at' => now(),
                ])->save();

                activity('adjustment')->performedOn($adj)->causedBy($actor)
                    ->withProperties(['opname' => $count->number])
                    ->log('ADJ disetujui di tingkat sesi opname'); // BR-OPN-06

                $this->poster->post($adj, $actor);
            }
        }

        $this->penandaHitung($count, $actor);

        return $this->tutup($count, $actor);
    }

    private function penandaHitung(StockCount $count, ?User $actor): void
    {
        $binDihitung = CountAssignment::query()->where('stock_count_id', $count->id)
            ->pluck('bin_id')->map(fn ($v) => (int) $v)->unique()->all();

        if ($count->count_type->isSpotCheck()) {
            $binBesar = CountLine::query()->where('stock_count_id', $count->id)
                ->where('variance_class', VarianceClass::Major->value)
                ->pluck('bin_id')->map(fn ($v) => (int) $v)->unique()->all();

            foreach (Bin::withoutGlobalScopes()->whereIn('id', $binBesar)->where('count_flag', false)->get() as $bin) {
                $this->binStatus->flagForCount($bin, true, $actor);
            }

            return;
        }

        foreach (Bin::withoutGlobalScopes()->whereIn('id', $binDihitung)->where('count_flag', true)->get() as $bin) {
            $this->binStatus->flagForCount($bin, false, $actor);
        }
    }

    private function tutup(StockCount $count, ?User $actor): StockCount
    {
        $kunci = null;

        if ($count->count_type === CountType::Monthly && $count->started_at !== null) {
            $tanggal = $count->started_at->copy()->subDay()->toDateString();
            $sekarang = $this->lock->current();

            if ($sekarang === null || $tanggal > $sekarang) {
                $kunci = $this->lock->handle($tanggal, 'Otomatis: sesi opname bulanan '.$count->number.' ditutup', $actor); // BR-STK-15
            }
        }

        $count->forceFill([
            'status' => StockCountStatus::Closed,
            'closed_at' => now(),
            'lock_date_set' => $kunci,
        ])->save();

        activity('count')->performedOn($count)->causedBy($actor)
            ->withProperties(['kunci_periode' => $kunci])
            ->log('Sesi opname ditutup'.($kunci !== null ? '; periode stok dikunci sampai '.$kunci : ''));

        return $count->refresh();
    }
}
