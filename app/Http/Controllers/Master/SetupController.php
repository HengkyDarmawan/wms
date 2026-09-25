<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Support\SetupWizard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Wizard setup awal company (A-191): daftar langkah, setujui ketentuan, tandai selesai (POST). */
class SetupController extends Controller
{
    public function index(SetupWizard $wizard): View
    {
        $this->authorize('company_setting.manage');

        return view('setup.index', [
            'steps' => $wizard->steps(),
            'progress' => $wizard->progress(),
            'completed' => $wizard->isCompleted(),
            'termsVersion' => SetupWizard::TERMS_VERSION,
        ]);
    }

    public function acceptTerms(Request $request, SetupWizard $wizard): RedirectResponse
    {
        $this->authorize('company_setting.manage');

        if (! $request->boolean('agree')) {
            return back()->withErrors(['agree' => __('Centang persetujuan terlebih dahulu.')]);
        }

        $wizard->acceptTerms($request->user());

        return back()->with('status', __('Ketentuan layanan & kebijakan privasi disetujui.'));
    }

    public function complete(Request $request, SetupWizard $wizard): RedirectResponse
    {
        $this->authorize('company_setting.manage');

        if ($wizard->progress()['required_left'] > 0) {
            return back()->withErrors(['setup' => __('Masih ada langkah wajib yang belum selesai.')]);
        }

        $wizard->complete($request->user());

        return redirect()->route('dashboard')->with('status', __('Setup awal selesai.'));
    }
}
