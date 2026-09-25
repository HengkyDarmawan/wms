<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 21-opname-penyesuaian §9 *Top selisih*: baris opname dengan selisih
 * mutlak terbesar dari sesi yang direkonsiliasi dalam periode, paling banyak
 * {@see self::BATAS} baris. Selisih dalam satuan dasar item masing-masing,
 * jadi urutannya per jumlah, bukan per nilai (D-07).
 */
class TopVarianceReport extends Report
{
    use PeriodFilter;

    public const BATAS = 100;

    public function key(): string
    {
        return 'top-selisih';
    }

    public function title(): string
    {
        return 'Top selisih opname';
    }

    public function permission(): string
    {
        return 'count.view';
    }

    public function description(): string
    {
        return 'Seratus baris opname dengan selisih terbesar (mutlak) dari sesi yang direkonsiliasi dalam periode: bin, item, angka sistem, hasil hitung, selisih, kelas, dan akar masalah.';
    }

    public function columns(): array
    {
        return [
            'opn' => 'Nomor OPN', 'gudang' => 'Gudang', 'bin' => 'Bin', 'item' => 'Item', 'pelacakan' => 'Lot / serial / potongan',
            'sistem' => 'Sistem', 'hitung' => 'Hitung', 'selisih' => 'Selisih', 'persen' => 'Selisih %', 'kelas' => 'Kelas selisih', 'akar' => 'Akar masalah',
        ];
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

        return CountLine::query()
            ->with('stockCount:id,number', 'bin:id,code,warehouse_id', 'bin.warehouse:id,code', 'item:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no')
            ->whereIn('stock_count_id', $sesi)
            ->whereNotNull('variance_qty')
            ->where('variance_qty', '!=', 0)
            ->when($gudang !== null, fn ($q) => $q->whereIn('bin_id', fn ($b) => $b->select('id')->from('bins')->whereIn('warehouse_id', $gudang)))
            ->orderByRaw('ABS(variance_qty) DESC')
            ->orderBy('id')
            ->limit(self::BATAS)
            ->get()
            ->map(fn (CountLine $l) => [
                'opn' => $l->stockCount?->number,
                'gudang' => $l->bin?->warehouse?->code,
                'bin' => $l->bin?->code,
                'item' => $l->item?->code,
                'pelacakan' => $l->trackingLabel() !== '' ? $l->trackingLabel() : '—',
                'sistem' => round((float) $l->system_qty, 4),
                'hitung' => round((float) $l->final_qty, 4),
                'selisih' => round((float) $l->variance_qty, 4),
                'persen' => $l->variance_pct !== null ? round((float) $l->variance_pct, 2) : '—',
                'kelas' => $l->variance_class?->label() ?? '—',
                'akar' => $l->root_cause?->label() ?? '—',
            ]);
    }
}
