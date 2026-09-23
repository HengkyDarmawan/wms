<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Beranda Super Admin: daftar company beserta status langganannya
 * (Blueprint §14). Isi data operasional company tidak dibuka dari sini.
 */
class PlatformDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'companies' => Company::query()
                ->with('subscription')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
