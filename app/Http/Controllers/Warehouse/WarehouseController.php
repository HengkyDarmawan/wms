<?php

declare(strict_types=1);

namespace App\Http\Controllers\Warehouse;

use App\Domain\Warehouse\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman gudang: pohon dan detail (12-warehouse §6). */
class WarehouseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Warehouse::class);

        return view('warehouse.warehouses.index');
    }

    public function show(Warehouse $warehouse): View
    {
        $this->authorize('view', $warehouse);

        return view('warehouse.warehouses.show', ['warehouse' => $warehouse]);
    }
}
