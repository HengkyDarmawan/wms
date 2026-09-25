<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\StockCount;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan *Akurasi stok* (Blueprint §6.9a, 21-opname-penyesuaian §9): per sesi
 * opname yang sudah direkonsiliasi — baris dihitung, baris cocok, akurasi %
 * (baris tanpa selisih / baris dihitung), selisih per kelas. Urut terbaru agar
 * trennya terbaca. Cakupan gudang lewat global scope OPN.
 */
class StockAccuracyReport extends Report
{
    public function key(): string
    {
        return 'akurasi-stok';
    }

    public function title(): string
    {
        return 'Akurasi stok';
    }

    public function permission(): string
    {
        return 'count.view';
    }

    public function description(): string
    {
        return 'Hasil opname per sesi: baris dihitung, baris cocok, akurasi %, dan jumlah selisih kecil/sedang/besar.';
    }

    public function columns(): array
    {
        return [
            'opn' => 'Nomor OPN', 'jenis' => 'Jenis', 'gudang' => 'Gudang', 'selesai' => 'Direkonsiliasi', 'status' => 'Status',
            'dihitung' => 'Baris dihitung', 'cocok' => 'Baris cocok', 'akurasi' => 'Akurasi %',
            'minor' => 'Selisih kecil', 'moderate' => 'Selisih sedang', 'major' => 'Selisih besar',
        ];
    }

    public function rows(array $filters): Collection
    {
        return StockCount::query()
            ->with('warehouses:id,code')
            ->whereIn('status', [StockCountStatus::Reconciling->value, StockCountStatus::Approved->value, StockCountStatus::Closed->value])
            ->orderByDesc('reconciled_at')->orderByDesc('id')
            ->get()
            ->map(function (StockCount $s) {
                $baris = $s->lines()->whereNotNull('final_qty')->get(['variance_qty', 'variance_class']);
                $dihitung = $baris->count();
                $cocok = $baris->filter(fn ($l) => abs((float) $l->variance_qty) < 0.00005)->count();

                return [
                    'opn' => $s->number,
                    'jenis' => $s->count_type?->label(),
                    'gudang' => $s->warehouses->pluck('code')->implode(', '),
                    'selesai' => $s->reconciled_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y'),
                    'status' => $s->status->label(),
                    'dihitung' => $dihitung,
                    'cocok' => $cocok,
                    'akurasi' => $dihitung > 0 ? round($cocok / $dihitung * 100, 2) : null,
                    'minor' => $baris->filter(fn ($l) => $l->variance_class?->value === 'minor')->count(),
                    'moderate' => $baris->filter(fn ($l) => $l->variance_class?->value === 'moderate')->count(),
                    'major' => $baris->filter(fn ($l) => $l->variance_class?->value === 'major')->count(),
                ];
            });
    }
}
