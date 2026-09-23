<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\ReasonCode;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman data referensi: alasan, penyimpanan, kendaraan, ekspedisi. */
class ReferenceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ReasonCode::class);

        return view('master.references.index');
    }
}
