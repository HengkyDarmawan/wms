<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\Uom;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman satuan dan kategori satuan (11-master §6). */
class UomController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Uom::class);

        return view('master.uoms.index');
    }
}
