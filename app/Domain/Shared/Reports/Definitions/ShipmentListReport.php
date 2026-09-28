<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\ShipmentMethod;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\ShipmentSearch;
use Illuminate\Support\Collection;

/** Laporan 15-picking-shipment §9 *Daftar pengiriman*: SJ per periode (dibuat atau berangkat), cakupan lewat global scope gudang. */
class ShipmentListReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'daftar-pengiriman';
    }

    public function title(): string
    {
        return 'Daftar pengiriman';
    }

    public function permission(): string
    {
        return 'shipment.view';
    }

    public function description(): string
    {
        return 'Surat jalan dalam rentang tanggal: tujuan, cara kirim & driver, status, tanggal berangkat dan terima, No. PO/GR klien.';
    }

    public function columns(): array
    {
        return ['nomor' => 'Nomor', 'gudang' => 'Gudang', 'tujuan' => 'Tujuan', 'cara' => 'Cara kirim', 'driver' => 'Driver', 'status' => 'Status', 'berangkat' => 'Tanggal kirim', 'diterima' => 'Diterima', 'penerima' => 'Penerima', 'po_klien' => 'No. PO klien', 'gr_klien' => 'No. GR klien'];
    }

    public function filters(): array
    {
        return [
            // A-313: cari nomor SJ, No. PO klien, atau No. GR klien.
            'cari' => ['label' => 'Nomor SJ / PO / GR klien'],
            'status' => ['label' => 'Status', 'options' => ShipmentStatus::options()],
            'destination_type' => ['label' => 'Tujuan', 'options' => DestinationType::options()],
            'shipment_method' => ['label' => 'Cara kirim', 'options' => ShipmentMethod::options()],
            'warehouse_id' => $this->penyaringGudang(),
        ] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);

        $daftar = Shipment::query()
            ->with('warehouse:id,code', 'destinationProject:id,code,name', 'destinationWarehouse:id,code,name', 'destinationVendor:id,name', 'proof:id,shipment_id,received_by_name,client_gr_number')
            ->where(fn ($q) => $q->whereBetween('shipped_at', [$dari, $sampai])->orWhere(fn ($w) => $w->whereNull('shipped_at')->whereBetween('created_at', [$dari, $sampai])))
            ->when(trim((string) ($filters['cari'] ?? '')) !== '', function ($q) use ($filters) {
                $cari = trim((string) $filters['cari']);

                $q->where(fn ($w) => $w->where('number', 'like', '%'.$cari.'%')
                    ->orWhereHas('proof', fn ($p) => $p->where('client_gr_number', 'like', '%'.$cari.'%'))
                    ->orWhereIn('id', ShipmentSearch::byClientPo($cari)));
            })
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['destination_type'] ?? '') !== '', fn ($q) => $q->where('destination_type', $filters['destination_type']))
            ->when(($filters['shipment_method'] ?? '') !== '', fn ($q) => $q->where('shipment_method', $filters['shipment_method']))
            ->when((int) ($filters['warehouse_id'] ?? 0) > 0, fn ($q) => $q->where('warehouse_id', (int) $filters['warehouse_id']))
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        $po = ShipmentSearch::clientPoByShipment($daftar->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $daftar->map(fn (Shipment $sj) => [
            'nomor' => $sj->number,
            'gudang' => $sj->warehouse?->code,
            'tujuan' => $sj->destination_type->label().': '.$sj->destinationLabel(),
            'cara' => $sj->shipment_method->label(),
            'driver' => $sj->driverLabel() ?: '—',
            'status' => $sj->status->label(),
            'berangkat' => $sj->shipped_at?->lokal()->format('d/m/Y H:i') ?? '—',
            'diterima' => $sj->delivered_at?->lokal()->format('d/m/Y H:i') ?? '—',
            'penerima' => $sj->proof?->received_by_name ?? '—',
            'po_klien' => $po[$sj->id] ?? '—',
            'gr_klien' => $sj->proof?->client_gr_number ?? '—',
        ]);
    }
}
