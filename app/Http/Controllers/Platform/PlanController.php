<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\SavePlan;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Paket langganan (Blueprint §14): daftar, buat, ubah, nonaktifkan (tanpa hapus, P-03). */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('platform.plans.index', ['plans' => Plan::query()->withCount('companies')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('platform.plans.form', ['plan' => new Plan(['trial_days' => 14, 'is_active' => true])]);
    }

    public function edit(int $plan): View
    {
        return view('platform.plans.form', ['plan' => Plan::query()->findOrFail($plan)]);
    }

    public function store(Request $request, SavePlan $action): RedirectResponse
    {
        return $this->simpan($request, null, $action);
    }

    public function update(Request $request, int $plan, SavePlan $action): RedirectResponse
    {
        return $this->simpan($request, Plan::query()->findOrFail($plan), $action);
    }

    private function simpan(Request $request, ?Plan $plan, SavePlan $action): RedirectResponse
    {
        /** @var PlatformUser $admin */
        $admin = $request->user('platform');

        try {
            $action->handle($plan, $request->only(['code', 'name', 'monthly_price', 'trial_days', 'wa_quota', 'storage_quota_mb']) + ['is_active' => $request->boolean('is_active')], $admin);
        } catch (PlatformRuleException $e) {
            return back()->withInput()->withErrors($e->fieldErrors ?: ['platform' => $e->getMessage()])->with('ruleCode', $e->rule);
        }

        return redirect()->route('platform.plans.index')->with('status', __('Paket tersimpan.'));
    }
}
