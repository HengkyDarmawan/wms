<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Count\Enums\RootCauseCategory;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 21-opname-penyesuaian §9 *Akar masalah per periode*: baris opname
 * berselisih dari sesi yang direkonsiliasi dalam periode, dikelompokkan per
 * kategori akar masalah (Katalog `root_cause_category`). Baris berselisih
 * tanpa akar masalah (boleh untuk selisih kecil, BR-OPN-07) tampil sebagai
 * "Belum diisi". Total selisih = jumlah |selisih| dalam satuan dasar.
 */
class RootCauseReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'akar-masalah';
    }

    public function title(): string
    {
        return 'Akar masalah selisih';
    }

    public function permission(): string
    {
        return 'count.view';
    }

    public function description(): string
    {
        return 'Baris opname berselisih dalam periode per akar masalah: jumlah baris, jumlah sesi, total selisih kurang, total selisih lebih, dan total selisih mutlak.';
    }

    public function columns(): array
    {
        return ['akar' => 'Akar masalah', 'baris' => 'Jumlah baris', 'sesi' => 'Sesi', 'kurang' => 'Total kurang', 'lebih' => 'Total lebih', 'mutlak' => 'Total |selisih|'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);

        $sesi = StockCount::query()
            ->whereIn('status', [StockCountStatus::Reconciling->value, StockCountStatus::Approved->value, StockCountStatus::Closed->value])
            ->whereBetween('reconciled_at', [$dari, $sampai])
            ->pluck('id');

        if ($sesi->isEmpty()) {
            return collect();
        }

        $urutan = array_flip(array_map(fn (RootCauseCategory $c) => $c->value, RootCauseCategory::cases()));

        return CountLine::query()
            ->whereIn('stock_count_id', $sesi)
            ->whereNotNull('variance_qty')
            ->where('variance_qty', '!=', 0)
            ->when($gudang !== null, fn ($q) => $q->whereIn('bin_id', fn ($b) => $b->select('id')->from('bins')->whereIn('warehouse_id', $gudang)))
            ->get(['id', 'stock_count_id', 'variance_qty', 'root_cause'])
            ->groupBy(fn (CountLine $l) => $l->root_cause?->value ?? '')
            ->map(fn (Collection $grup, string $akar) => [
                'urut' => $urutan[$akar] ?? PHP_INT_MAX,
                'akar' => RootCauseCategory::tryFrom($akar)?->label() ?? 'Belum diisi',
                'baris' => $grup->count(),
                'sesi' => $grup->pluck('stock_count_id')->unique()->count(),
                'kurang' => round((float) $grup->sum(fn (CountLine $l) => min(0.0, (float) $l->variance_qty)), 4),
                'lebih' => round((float) $grup->sum(fn (CountLine $l) => max(0.0, (float) $l->variance_qty)), 4),
                'mutlak' => round((float) $grup->sum(fn (CountLine $l) => abs((float) $l->variance_qty)), 4),
            ])
            ->sortBy('urut')
            ->map(fn (array $r) => array_diff_key($r, ['urut' => true]))
            ->values();
    }
}
