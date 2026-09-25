<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan 21-opname-penyesuaian §9 *Tren akurasi*: akurasi per bulan × gudang
 * dari sesi yang sudah direkonsiliasi (Rekonsiliasi/Disetujui/Ditutup) dengan
 * tanggal rekonsiliasi dalam periode. Rumusnya sama dengan *Akurasi stok*:
 * baris tanpa selisih ÷ baris dihitung. Gudang baris dibaca dari bin-nya, jadi
 * sesi lintas gudang terbagi per gudang.
 *
 * Bawaan periode **12 bulan terakhir** (awal bulan 11 bulan lalu s.d. hari
 * ini), bukan bulan berjalan seperti laporan lain: tren satu bulan tidak
 * bermakna. Cakupan gudang lewat global scope OPN, lalu baris dibatasi ke
 * bin di gudang dalam cakupan/terpilih.
 */
class AccuracyTrendReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'tren-akurasi';
    }

    public function title(): string
    {
        return 'Tren akurasi opname';
    }

    public function permission(): string
    {
        return 'count.view';
    }

    public function description(): string
    {
        return 'Akurasi opname per bulan dan gudang (bawaan 12 bulan terakhir): jumlah sesi, baris dihitung, baris cocok, akurasi %, dan selisih besar.';
    }

    public function columns(): array
    {
        return ['bulan' => 'Bulan', 'gudang' => 'Gudang', 'sesi' => 'Sesi', 'dihitung' => 'Baris dihitung', 'cocok' => 'Baris cocok', 'akurasi' => 'Akurasi %', 'besar' => 'Selisih besar'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        if (trim((string) ($filters['date_from'] ?? '')) === '') {
            $filters['date_from'] = now()->lokal()->subMonthsNoOverflow(11)->startOfMonth()->toDateString();
        }

        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);

        $sesi = StockCount::query()
            ->whereIn('status', [StockCountStatus::Reconciling->value, StockCountStatus::Approved->value, StockCountStatus::Closed->value])
            ->whereBetween('reconciled_at', [$dari, $sampai])
            ->pluck('reconciled_at', 'id');

        if ($sesi->isEmpty()) {
            return collect();
        }

        return CountLine::query()
            ->with('bin:id,warehouse_id', 'bin.warehouse:id,code')
            ->whereIn('stock_count_id', $sesi->keys())
            ->whereNotNull('final_qty')
            ->when($gudang !== null, fn ($q) => $q->whereIn('bin_id', fn ($b) => $b->select('id')->from('bins')->whereIn('warehouse_id', $gudang)))
            ->get(['id', 'stock_count_id', 'bin_id', 'variance_qty', 'variance_class'])
            ->groupBy(fn (CountLine $l) => $sesi[$l->stock_count_id]->lokal()->format('Y-m').'|'.($l->bin?->warehouse?->code ?? '—'))
            ->map(function (Collection $grup, string $kunci) {
                [$bulan, $kode] = explode('|', $kunci, 2);
                $dihitung = $grup->count();
                $cocok = $grup->filter(fn (CountLine $l) => ! $l->hasVariance())->count();

                return [
                    'urut' => $kunci,
                    'bulan' => substr($bulan, 5, 2).'/'.substr($bulan, 0, 4),
                    'gudang' => $kode,
                    'sesi' => $grup->pluck('stock_count_id')->unique()->count(),
                    'dihitung' => $dihitung,
                    'cocok' => $cocok,
                    'akurasi' => round($cocok / $dihitung * 100, 2),
                    'besar' => $grup->filter(fn (CountLine $l) => $l->variance_class === VarianceClass::Major)->count(),
                ];
            })
            ->sortBy('urut')
            ->map(fn (array $r) => array_diff_key($r, ['urut' => true]))
            ->values();
    }
}
