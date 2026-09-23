<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Beranda back-office. Isi dashboard sesungguhnya dibangun modul-modul berikutnya;
 * Fase modul Access hanya menampilkan konteks company dan cakupan user.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'assignments' => $user->validAssignments(),
            'company' => tenant(),
        ]);
    }
}
