<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shipment\Enums\ClientDecision;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Models\PickTaskLine;
use Illuminate\Support\Facades\DB;

/**
 * Sisa baris REQ bersumber stok yang masih perlu dipetik (A-204):
 *
 *   diminta − diambil (PCK selesai) − dialokasikan (PCK berjalan)
 *   + selisih yang diputus "masih dibutuhkan" (DSC selesai, termasuk keberatan)
 *
 * Dengan begitu short pick (BR-SJ-02), `reship`/`still_needed` (BR-SJ-10), dan
 * keberatan yang membuka lagi baris (A-198) bisa dipetik ulang lewat PCK
 * baru, tanpa memetik dua kali yang sudah berjalan.
 */
class RequestLineOutstanding
{
    public const EPS = 0.00005;

    public function qty(MaterialRequestLine $line): float
    {
        $milikReq = fn ($q) => $q->withoutGlobalScopes()->forSource('material_request', (int) $line->material_request_id);

        $diambil = (float) PickTaskLine::query()->where('source_line_id', $line->id)
            ->whereHas('pickTask', fn ($q) => $milikReq($q)->where('status', PickTaskStatus::Completed->value))
            ->sum('qty_picked');

        $berjalan = (float) PickTaskLine::query()->where('source_line_id', $line->id)
            ->whereHas('pickTask', fn ($q) => $milikReq($q)->whereIn('status', [PickTaskStatus::Pending->value, PickTaskStatus::InProgress->value]))
            ->sum('qty_allocated');

        $kembali = (float) DB::table('delivery_discrepancy_lines as dl')
            ->join('delivery_discrepancies as d', 'd.id', '=', 'dl.delivery_discrepancy_id')
            ->join('shipment_lines as sl', 'sl.id', '=', 'dl.shipment_line_id')
            ->join('pick_task_lines as pl', 'pl.id', '=', 'sl.pick_task_line_id')
            ->join('pick_tasks as pt', 'pt.id', '=', 'pl.pick_task_id')
            ->where('pt.source_type', 'material_request')
            ->where('pt.source_id', $line->material_request_id)
            ->where('pl.source_line_id', $line->id)
            ->where('d.status', DiscrepancyStatus::Resolved->value)
            ->where('dl.client_decision', ClientDecision::StillNeeded->value)
            ->sum('dl.qty_base');

        $sisa = round((float) $line->qty_base - $diambil - $berjalan + $kembali, 4);

        return $sisa > self::EPS ? $sisa : 0.0;
    }
}
