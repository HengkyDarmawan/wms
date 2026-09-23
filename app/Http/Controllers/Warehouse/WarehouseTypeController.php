<?php

declare(strict_types=1);

namespace App\Http\Controllers\Warehouse;

use App\Domain\Warehouse\Models\WarehouseType;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman master tipe gudang (12-warehouse §6). */
class WarehouseTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', WarehouseType::class);

        return view('warehouse.types.index');
    }
}
