<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shipment;

use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman modul Picking & Shipment (15-picking-shipment §6). */
class ShipmentController extends Controller
{
    public function picks(): View
    {
        $this->authorize('viewAny', PickTask::class);

        return view('shipment.picks');
    }

    public function pick(PickTask $pickTask): View
    {
        $this->authorize('view', $pickTask);

        return view('shipment.pick', ['task' => $pickTask]);
    }

    public function index(): View
    {
        $this->authorize('viewAny', Shipment::class);

        return view('shipment.index');
    }

    public function create(): View
    {
        $this->authorize('create', Shipment::class);

        return view('shipment.create');
    }

    public function show(Shipment $shipment): View
    {
        $this->authorize('view', $shipment);

        return view('shipment.show', ['sj' => $shipment]);
    }

    public function discrepancies(): View
    {
        $this->authorize('viewAny', DeliveryDiscrepancy::class);

        return view('shipment.discrepancies');
    }
}
