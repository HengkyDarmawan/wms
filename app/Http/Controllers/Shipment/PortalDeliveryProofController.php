<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shipment;

use App\Domain\Shipment\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Bukti terima SJ oleh admin site klien di portal (A-312, 15-picking-shipment §6). */
class PortalDeliveryProofController extends Controller
{
    public function __invoke(Shipment $shipment): View
    {
        $this->authorize('confirmDelivery', $shipment);

        return view('portal.shipments.proof', ['sj' => $shipment]);
    }
}
