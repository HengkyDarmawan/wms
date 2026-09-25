<?php

declare(strict_types=1);

namespace App\Domain\Request\Support;

use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Models\Shipment;
use Illuminate\Support\Collection;

/** SJ yang membawa barang sebuah REQ beserta bukti terima & tanggapan pemohon (BR-REQ-10). */
class RequestDeliveries
{
    /** @return Collection<int, Shipment> */
    public function for(MaterialRequest $request): Collection
    {
        return Shipment::query()->withoutGlobalScopes()
            ->whereHas('lines.pickTaskLine.pickTask', fn ($q) => $q->where('source_type', 'material_request')->where('source_id', $request->id))
            ->with(['proof.lines.shipmentLine.pickTaskLine.item:id,code,name'])
            ->orderBy('id')
            ->get();
    }
}
