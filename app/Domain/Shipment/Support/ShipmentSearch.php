<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A-313: mencari SJ lewat No. PO klien yang tercatat di REQ yang dimuatnya
 * (REQ → PCK → baris SJ). Dipakai daftar SJ dan laporan daftar pengiriman.
 */
final class ShipmentSearch
{
    /** Subquery `shipments.id` yang memuat REQ ber-No. PO klien mirip `$teks`. */
    public static function byClientPo(string $teks): Builder
    {
        return self::jalur()
            ->select('shipment_lines.shipment_id')
            ->where('material_requests.client_po_number', 'like', '%'.$teks.'%');
    }

    /**
     * No. PO klien per SJ dalam satu query (laporan, tanpa N+1).
     *
     * @param  array<int, int>  $shipmentIds
     * @return array<int, string> shipment_id => "PO-1, PO-2"
     */
    public static function clientPoByShipment(array $shipmentIds): array
    {
        return self::jalur()
            ->whereIn('shipment_lines.shipment_id', $shipmentIds)
            ->whereNotNull('material_requests.client_po_number')
            ->distinct()
            ->get(['shipment_lines.shipment_id', 'material_requests.client_po_number'])
            ->groupBy('shipment_id')
            ->map(fn ($baris) => $baris->pluck('client_po_number')->unique()->implode(', '))
            ->all();
    }

    private static function jalur(): Builder
    {
        return DB::table('shipment_lines')
            ->join('pick_task_lines', 'pick_task_lines.id', '=', 'shipment_lines.pick_task_line_id')
            ->join('pick_tasks', 'pick_tasks.id', '=', 'pick_task_lines.pick_task_id')
            ->join('material_requests', 'material_requests.id', '=', 'pick_tasks.source_id')
            ->where('pick_tasks.source_type', 'material_request');
    }
}
