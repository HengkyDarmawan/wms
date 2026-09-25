<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Models\DeliveryDiscrepancyLine;
use Illuminate\Support\Collection;

/** Laporan 15-picking-shipment §9 *Posisi barang rusak & selisih* (BR-SJ-06, BR-SJ-10): baris DSC dan disposisinya. */
class DamagedGoodsPositionReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'posisi-rusak-selisih';
    }

    public function title(): string
    {
        return 'Posisi barang rusak & selisih';
    }

    public function permission(): string
    {
        return 'shipment.view';
    }

    public function description(): string
    {
        return 'Setiap baris selisih pengiriman: jenis (kurang/rusak), jumlah, disposisi yang dipilih, status DSC, dan umurnya. Bawaan hanya DSC terbuka.';
    }

    public function columns(): array
    {
        return ['dsc' => 'DSC', 'sj' => 'SJ', 'gudang' => 'Gudang', 'proyek' => 'Proyek', 'item' => 'Item', 'jenis' => 'Jenis', 'jumlah' => 'Jumlah', 'disposisi' => 'Disposisi', 'status' => 'Status DSC', 'umur' => 'Umur (hari)'];
    }

    public function filters(): array
    {
        return [
            'warehouse_id' => $this->penyaringGudang(),
            'min_age_days' => ['label' => 'Umur minimal (hari)'],
            'include_resolved' => ['label' => 'Termasuk yang selesai', 'options' => ['1' => 'Ya']],
        ];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);
        $minimal = (int) ($filters['min_age_days'] ?? 0);
        $semua = ($filters['include_resolved'] ?? '') === '1';
        $hariIni = now()->startOfDay();

        return DeliveryDiscrepancyLine::query()
            ->with('discrepancy:id,number,shipment_id,status,created_at', 'discrepancy.shipment:id,number,warehouse_id,destination_project_id', 'discrepancy.shipment.warehouse:id,code', 'discrepancy.shipment.destinationProject:id,code', 'shipmentLine.pickTaskLine.item:id,code')
            ->whereHas('discrepancy', fn ($q) => $q
                ->when(! $semua, fn ($d) => $d->where('status', DiscrepancyStatus::Open->value))
                ->whereHas('shipment', fn ($s) => $s->when($gudang !== null, fn ($w) => $w->whereIn('warehouse_id', $gudang))))
            ->get()
            ->map(fn (DeliveryDiscrepancyLine $l) => [
                'dsc' => $l->discrepancy?->number,
                'sj' => $l->discrepancy?->shipment?->number,
                'gudang' => $l->discrepancy?->shipment?->warehouse?->code,
                'proyek' => $l->discrepancy?->shipment?->destinationProject?->code ?? '—',
                'item' => $l->shipmentLine?->pickTaskLine?->item?->code,
                'jenis' => $l->discrepancy_type?->label(),
                'jumlah' => round((float) $l->qty_base, 4),
                'disposisi' => $l->disposition?->label() ?? '—',
                'status' => $l->discrepancy?->status?->label(),
                'umur' => (int) $l->discrepancy?->created_at?->startOfDay()->diffInDays($hariIni),
            ])
            ->filter(fn ($r) => $r['umur'] >= $minimal)
            ->sortByDesc('umur')
            ->values();
    }
}
