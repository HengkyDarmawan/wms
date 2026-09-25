<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustmentLine;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 21-opname-penyesuaian §9 *ADJ per alasan*: penyesuaian yang
 * **diposting** dalam periode (hanya status Diposting yang menggerakkan stok),
 * per kode alasan. Alasan baris dipakai bila ada, selain itu alasan kepala —
 * sama dengan yang dicatat ke kartu stok (`AdjustmentPoster`). Hanya jumlah
 * dalam satuan dasar, tanpa nilai uang (D-07).
 */
class AdjustmentByReasonReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'adj-per-alasan';
    }

    public function title(): string
    {
        return 'Penyesuaian per alasan';
    }

    public function permission(): string
    {
        return 'adjustment.view';
    }

    public function description(): string
    {
        return 'Penyesuaian stok yang diposting dalam periode per alasan: jumlah ADJ, jumlah baris, total jumlah masuk (+), dan total jumlah keluar (−).';
    }

    public function columns(): array
    {
        return ['alasan' => 'Alasan', 'adj' => 'Jumlah ADJ', 'baris' => 'Jumlah baris', 'masuk' => 'Total masuk (+)', 'keluar' => 'Total keluar (−)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);

        $baris = StockAdjustment::query()
            ->with('reason:id,code,label', 'lines:id,stock_adjustment_id,qty_delta,reason_code_id', 'lines.reason:id,code,label')
            ->where('status', StockAdjustmentStatus::Posted->value)
            ->whereBetween('posted_at', [$dari, $sampai])
            ->when($gudang !== null, fn ($q) => $q->whereIn('warehouse_id', $gudang))
            ->get()
            ->flatMap(fn (StockAdjustment $a) => $a->lines->map(fn (StockAdjustmentLine $l) => [
                'adj' => (int) $a->id,
                'alasan' => $l->reason ?? $a->reason,
                'delta' => (float) $l->qty_delta,
            ]));

        return $baris->groupBy(fn (array $b) => (string) ($b['alasan']?->id ?? ''))
            ->map(function (Collection $grup) {
                $alasan = $grup->first()['alasan'];

                return [
                    'alasan' => $alasan !== null ? $alasan->code.' — '.$alasan->label : '—',
                    'adj' => $grup->pluck('adj')->unique()->count(),
                    'baris' => $grup->count(),
                    'masuk' => round((float) $grup->sum(fn (array $b) => max(0.0, $b['delta'])), 4),
                    'keluar' => round((float) $grup->sum(fn (array $b) => max(0.0, -$b['delta'])), 4),
                ];
            })
            ->sortBy('alasan')
            ->values();
    }
}
