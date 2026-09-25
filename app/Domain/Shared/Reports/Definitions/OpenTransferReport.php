<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Transfer\Models\Transfer;
use Illuminate\Support\Collection;

/**
 * Laporan 22-retur-transfer §9 *TRF terbuka per gudang & umur*: transfer yang
 * belum final (bukan Selesai/Ditolak/Dibatalkan, `Transfer::scopeOpen`),
 * dengan umur sejak diajukan dan jumlah dikirim/diterima per dokumen. Penyaring
 * gudang mencocokkan gudang asal **atau** tujuan, sama dengan cakupannya (A-109).
 */
class OpenTransferReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'trf-terbuka';
    }

    public function title(): string
    {
        return 'TRF terbuka';
    }

    public function permission(): string
    {
        return 'transfer.view';
    }

    public function description(): string
    {
        return 'Transfer yang belum selesai: gudang asal dan tujuan, status, jumlah diminta/dikirim/diterima, dan umurnya dalam hari sejak diajukan.';
    }

    public function columns(): array
    {
        return ['trf' => 'TRF', 'dari' => 'Dari gudang', 'ke' => 'Ke gudang', 'status' => 'Status', 'diminta' => 'Diminta', 'dikirim' => 'Dikirim', 'diterima' => 'Diterima', 'dibuat' => 'Diajukan', 'umur' => 'Umur (hari)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $hariIni = now()->lokal()->startOfDay();

        return Transfer::query()
            ->with('fromWarehouse:id,code', 'toWarehouse:id,code')
            ->withSum('lines', 'qty_base')
            ->withSum('lines', 'qty_shipped')
            ->withSum('lines', 'qty_received')
            ->open()
            ->when($gudang !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('from_warehouse_id', $gudang)->orWhereIn('to_warehouse_id', $gudang)))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Transfer $t) => [
                'trf' => $t->number,
                'dari' => $t->fromWarehouse?->code,
                'ke' => $t->toWarehouse?->code,
                'status' => $t->status->label(),
                'diminta' => round((float) $t->lines_sum_qty_base, 4),
                'dikirim' => round((float) $t->lines_sum_qty_shipped, 4),
                'diterima' => round((float) $t->lines_sum_qty_received, 4),
                'dibuat' => $t->created_at?->lokal()->format('d/m/Y H:i'),
                'umur' => (int) $t->created_at?->lokal()->startOfDay()->diffInDays($hariIni),
            ]);
    }
}
