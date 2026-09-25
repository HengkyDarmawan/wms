<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Models\PickTaskLine;
use Illuminate\Support\Collection;

/** Laporan 15-picking-shipment §9 *Short pick* (BR-SJ-01): baris picking selesai yang diambil kurang dari alokasi. */
class ShortPickReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'short-pick';
    }

    public function title(): string
    {
        return 'Short pick';
    }

    public function permission(): string
    {
        return 'pick.view';
    }

    public function description(): string
    {
        return 'Baris tugas picking yang selesai dengan jumlah diambil di bawah alokasi, beserta selisih dan alasannya.';
    }

    public function columns(): array
    {
        return ['pck' => 'PCK', 'gudang' => 'Gudang', 'selesai' => 'Selesai', 'item' => 'Item', 'bin' => 'Bin', 'dialokasikan' => 'Dialokasikan', 'diambil' => 'Diambil', 'selisih' => 'Selisih', 'alasan' => 'Alasan'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);

        return PickTaskLine::query()
            ->with('pickTask:id,number,warehouse_id,status,completed_at', 'pickTask.warehouse:id,code', 'item:id,code', 'bin:id,code', 'shortReason:id,label')
            ->whereColumn('qty_picked', '<', 'qty_allocated')
            ->whereHas('pickTask', fn ($q) => $q
                ->where('status', PickTaskStatus::Completed->value)
                ->whereBetween('completed_at', [$dari, $sampai])
                ->when($gudang !== null, fn ($w) => $w->whereIn('warehouse_id', $gudang)))
            ->get()
            ->map(fn (PickTaskLine $l) => [
                'pck' => $l->pickTask?->number,
                'gudang' => $l->pickTask?->warehouse?->code,
                'selesai' => $l->pickTask?->completed_at?->lokal()->format('d/m/Y H:i'),
                'item' => $l->item?->code,
                'bin' => $l->bin?->code,
                'dialokasikan' => round((float) $l->qty_allocated, 4),
                'diambil' => round((float) $l->qty_picked, 4),
                'selisih' => round($l->shortQty(), 4),
                'alasan' => $l->shortReason?->label ?? '—',
            ])
            ->sortByDesc('selesai')
            ->values();
    }
}
