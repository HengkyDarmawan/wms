<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use Illuminate\Support\Collection;

/**
 * Laporan *Permintaan terbuka & posisi barang rusak* (Blueprint §6.9a,
 * BR-SJ-10): baris REQ terbuka (backorder, menunggu approval, lewat tanggal
 * dibutuhkan) dan DSC terbuka beserta umurnya. Cakupan REQ lewat global scope
 * dokumen (BR-ACC-05).
 */
class OpenRequestReport extends Report
{
    public function key(): string
    {
        return 'permintaan-terbuka';
    }

    public function title(): string
    {
        return 'Permintaan terbuka & barang rusak';
    }

    public function permission(): string
    {
        return 'request.view';
    }

    public function description(): string
    {
        return 'Baris REQ yang belum terpenuhi (status, sisa, lewat tanggal dibutuhkan) dan selisih pengiriman terbuka beserta umurnya.';
    }

    public function columns(): array
    {
        return [
            'jenis' => 'Jenis', 'dokumen' => 'Dokumen', 'proyek' => 'Proyek', 'item' => 'Item', 'status' => 'Status',
            'sisa' => 'Sisa / jumlah', 'dibutuhkan' => 'Dibutuhkan', 'lewat_hari' => 'Lewat / umur (hari)',
        ];
    }

    public function filters(): array
    {
        return ['only_late' => ['label' => 'Hanya yang lewat tanggal / DSC > 3 hari', 'options' => ['1' => 'Ya']]];
    }

    public function rows(array $filters): Collection
    {
        $hariIni = now()->startOfDay();
        $telat = ($filters['only_late'] ?? '') === '1';

        $req = MaterialRequestLine::query()
            ->with('request:id,number,project_id,status,required_date', 'request.project:id,code', 'item:id,code')
            ->where('status', RequestLineStatus::Open->value)
            ->whereHas('request', fn ($q) => $q->whereIn('status', [
                MaterialRequestStatus::UnderReview->value, MaterialRequestStatus::PendingApproval->value, MaterialRequestStatus::Approved->value,
                MaterialRequestStatus::InProgress->value, MaterialRequestStatus::PartiallyFulfilled->value,
            ]))
            ->get()
            ->map(function (MaterialRequestLine $l) use ($hariIni) {
                $butuh = $l->required_date ?? $l->request?->required_date;
                $sisa = round((float) $l->qty_base - (float) $l->qty_shipped, 4);

                return [
                    'jenis' => 'REQ',
                    'dokumen' => $l->request?->number,
                    'proyek' => $l->request?->project?->code,
                    'item' => $l->item?->code ?? $l->displayName(),
                    'status' => $l->request?->status->label().($l->fulfillment_source !== null ? ' · '.$l->fulfillment_source->label() : ''),
                    'sisa' => $sisa,
                    'dibutuhkan' => $butuh?->format('d/m/Y'),
                    'lewat_hari' => $butuh !== null && $butuh->lt($hariIni) ? (int) $butuh->diffInDays($hariIni) : 0,
                ];
            })
            ->filter(fn ($r) => $r['sisa'] > 0);

        $dsc = DeliveryDiscrepancy::query()
            ->with('shipment:id,number,destination_project_id', 'shipment.destinationProject:id,code', 'lines.shipmentLine.pickTaskLine.item:id,code')
            ->where('status', DiscrepancyStatus::Open->value)
            ->get()
            ->flatMap(fn (DeliveryDiscrepancy $d) => $d->lines->map(fn ($l) => [
                'jenis' => 'DSC',
                'dokumen' => $d->number.' ('.$d->shipment?->number.')',
                'proyek' => $d->shipment?->destinationProject?->code,
                'item' => $l->shipmentLine?->pickTaskLine?->item?->code,
                'status' => $d->origin->label().' · '.($l->discrepancy_type?->label() ?? ''),
                'sisa' => round((float) $l->qty_base, 4),
                'dibutuhkan' => null,
                'lewat_hari' => (int) $d->created_at?->startOfDay()->diffInDays($hariIni),
            ]));

        return $req->concat($dsc)
            ->when($telat, fn ($c) => $c->filter(fn ($r) => $r['jenis'] === 'REQ' ? $r['lewat_hari'] > 0 : $r['lewat_hari'] > 3))
            ->sortByDesc('lewat_hari')
            ->values();
    }
}
