<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\Vendor;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman vendor (11-master §6). */
class VendorController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Vendor::class);

        return view('master.vendors.index');
    }
}
