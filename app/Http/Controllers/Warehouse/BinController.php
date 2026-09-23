<?php

declare(strict_types=1);

namespace App\Http\Controllers\Warehouse;

use App\Domain\Warehouse\Models\Bin;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman daftar bin lintas gudang (12-warehouse §6). */
class BinController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Bin::class);

        return view('warehouse.bins.index');
    }
}
