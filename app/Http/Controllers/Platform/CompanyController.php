<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\ChangeCompanyStatus;
use App\Domain\Platform\Actions\CreateCompany;
use App\Domain\Platform\Actions\EnterSupportAccess;
use App\Domain\Platform\Actions\ProvisionCompany;
use App\Domain\Platform\Actions\SetFeatureFlag;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\SubscriptionInvoice;
use App\Domain\Platform\Models\SupportAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Layar company Super Admin (17-platform-login §6.1): buat company, detail
 * (langganan, tagihan, flag fitur, akses dukungan), lanjutkan provisioning,
 * tangguhkan / aktifkan kembali. Semua perubahan lewat POST (NFR-02).
 */
class CompanyController extends Controller
{
    public function create(): View
    {
        return view('platform.companies.create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('name')->get(),
            'timezones' => ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'],
            'domain' => config('tenancy.central_domains.0', 'wms.test'),
        ]);
    }

    public function store(Request $request, CreateCompany $action): RedirectResponse
    {
        try {
            $company = $action->handle($request->only(['code', 'name', 'subdomain', 'timezone', 'plan_id', 'trial_days', 'admin_name', 'admin_email']), $this->admin($request));
        } catch (PlatformRuleException $e) {
            return $this->gagal($e);
        }

        return redirect()->route('platform.companies.show', $company->id)->with('status', $company->provisioningError() === null
            ? __('Company :kode siap; undangan dikirim ke Admin Company.', ['kode' => $company->code])
            : __('Company :kode dibuat tetapi provisioning gagal; periksa galat lalu lanjutkan.', ['kode' => $company->code]));
    }

    public function show(int $company): View
    {
        $c = Company::query()->with('plan', 'subscription.plan', 'featureFlags')->findOrFail($company);

        return view('platform.companies.show', [
            'company' => $c,
            'invoices' => SubscriptionInvoice::query()->whereHas('subscription', fn ($q) => $q->where('company_id', $c->id))
                ->with(['payments' => fn ($q) => $q->latest('id')])->orderByDesc('period_start')->get(),
            'supportAccesses' => SupportAccess::query()->where('company_id', $c->id)->with('platformUser:id,name')
                ->latest('id')->limit(10)->get(),
            'flags' => SetFeatureFlag::KEYS,
            'enabled' => $c->featureFlags->where('enabled', true)->pluck('key')->all(),
            'history' => PlatformAuditLog::query()->where('subject_type', $c->getMorphClass())->where('subject_id', $c->id)
                ->latest('id')->limit(20)->get(),
        ]);
    }

    public function provision(Request $request, int $company, ProvisionCompany $action): RedirectResponse
    {
        try {
            $c = $action->handle(Company::query()->findOrFail($company), $this->admin($request));
        } catch (PlatformRuleException $e) {
            return $this->gagal($e);
        }

        return back()->with('status', $c->provisioningError() === null ? __('Company siap dipakai.') : __('Provisioning masih gagal.'));
    }

    public function suspend(Request $request, int $company, ChangeCompanyStatus $action): RedirectResponse
    {
        try {
            $action->suspend(Company::query()->findOrFail($company), (string) $request->input('reason'), $this->admin($request));
        } catch (PlatformRuleException $e) {
            return $this->gagal($e);
        }

        return back()->with('status', __('Company ditangguhkan (hanya-baca).'));
    }

    public function reactivate(Request $request, int $company, ChangeCompanyStatus $action): RedirectResponse
    {
        try {
            $action->reactivate(Company::query()->findOrFail($company), $this->admin($request));
        } catch (PlatformRuleException $e) {
            return $this->gagal($e);
        }

        return back()->with('status', __('Company diaktifkan kembali.'));
    }

    public function flag(Request $request, int $company, SetFeatureFlag $action): RedirectResponse
    {
        try {
            $action->handle(Company::query()->findOrFail($company), (string) $request->input('key'), $request->boolean('enabled'), $this->admin($request));
        } catch (PlatformRuleException $e) {
            return $this->gagal($e);
        }

        return back()->with('status', __('Flag fitur tersimpan.'));
    }

    public function support(Request $request, int $company, EnterSupportAccess $action): RedirectResponse
    {
        try {
            $tautan = $action->handle(Company::query()->findOrFail($company), $this->admin($request), $request->getScheme(), $request->getPort());
        } catch (PlatformRuleException $e) {
            return $this->gagal($e);
        }

        return redirect()->away($tautan);
    }

    private function admin(Request $request): PlatformUser
    {
        /** @var PlatformUser $admin */
        $admin = $request->user('platform');

        return $admin;
    }

    private function gagal(PlatformRuleException $e): RedirectResponse
    {
        return back()->withInput()->withErrors($e->fieldErrors ?: ['platform' => $e->getMessage()])->with('ruleCode', $e->rule);
    }
}
