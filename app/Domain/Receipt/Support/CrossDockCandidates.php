<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Request\Enums\FulfillmentSource;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Models\MaterialRequestLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Titik sambung cross-dock (BR-REQ-08, BR-SJ-03) — **saran saja di Fase 1**.
 *
 * Aturan menulis barang backorder yang tiba "otomatis direservasi ke REQ
 * penunggu, lalu disarankan cross-dock", dan cross-dock berarti langsung ke
 * Loading Area tanpa PCK. Dokumen belum menjelaskan bagaimana barang di Loading
 * Area tanpa PCK masuk ke SJ (SJ dibangun dari baris PCK), dan GRN belum bisa
 * merujuk PRQ/TRF karena modulnya belum ada. Maka kelas ini hanya menemukan
 * baris REQ yang menunggu item yang sama di gudang ini, urut tanggal
 * dibutuhkan, untuk ditampilkan di detail GRN (19-receipt-putaway §13).
 */
class CrossDockCandidates
{
    /** @return Collection<int, MaterialRequestLine> */
    public function waitingFor(int $itemId, int $warehouseId, int $limit = 5): Collection
    {
        return MaterialRequestLine::query()
            ->with('request:id,number,status,project_id')
            ->where('item_id', $itemId)
            ->where('source_warehouse_id', $warehouseId)
            ->where('status', RequestLineStatus::Open->value)
            ->where(fn (Builder $q) => $q
                ->where('qty_backorder', '>', 0)
                ->orWhereIn('fulfillment_source', [
                    FulfillmentSource::Purchase->value,
                    FulfillmentSource::Transfer->value,
                ]))
            ->whereHas('request', fn (Builder $q) => $q->whereIn('status', [
                MaterialRequestStatus::Approved->value,
                MaterialRequestStatus::InProgress->value,
                MaterialRequestStatus::PartiallyFulfilled->value,
            ]))
            ->orderBy('required_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
