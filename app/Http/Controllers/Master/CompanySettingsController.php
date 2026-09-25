<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Layar Pengaturan company (11-master §6, A-230); isinya komponen Livewire. */
class CompanySettingsController extends Controller
{
    public function edit(): View
    {
        $this->authorize('company_setting.view');

        return view('master.settings');
    }
}
