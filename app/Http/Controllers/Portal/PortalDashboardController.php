<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Beranda portal klien (BR-PRJ-07). Daftar proyek & permintaan dibangun modul
 * Master dan Request; di modul Access hanya kerangka dan identitas klien.
 */
class PortalDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('portal.dashboard', [
            'user' => $request->user(),
            'company' => tenant(),
        ]);
    }
}
