<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Actions\StartSupportSession;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\Models\SupportAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-SUB-02 / BR-SUB-03 (Arsitektur §3.1, 17-platform-login §5.1):
 *  - trial/active : normal
 *  - past_due     : normal + spanduk
 *  - suspended    : hanya permintaan baca (termasuk cari/filter/halaman Livewire, A-196);
 *                   proses masuk & unggah bukti bayar tetap boleh
 *  - terminated   : hanya Admin Company, hanya layar ekspor (laporan), tagihan, profil
 *                   (A-181; siapa yang boleh masuk ditegakkan LoginController)
 *
 * Status efektif = yang paling berat antara status langganan dan status
 * company (penangguhan manual Super Admin, A-179); company `provisioning`
 * belum bisa dipakai (503). Sesi akses dukungan Super Admin selalu hanya-baca
 * dan berakhir bersama izinnya (BR-SUB-04, A-180).
 */
class EnsureSubscriptionState
{
    /** Route autentikasi: selalu boleh agar user tetap bisa masuk & keluar. */
    private const AUTH_ROUTES = [
        'login', 'login.store',
        'portal.login', 'portal.login.store',
        'two-factor', 'two-factor.store',
        'logout',
        'password.request', 'password.email', 'password.reset', 'password.update',
        'invitation.show', 'invitation.store',
        'support.enter', 'support.enter.store',
    ];

    /** Route yang tetap boleh menulis saat langganan ditangguhkan. */
    private const WRITE_ALLOWED_WHEN_SUSPENDED = [
        'billing.payment.store',
    ];

    /** A-181: layar yang tetap terbuka bagi Admin Company saat langganan diakhiri (ekspor data). */
    private const READ_ALLOWED_WHEN_TERMINATED = [
        'dashboard', 'reports.index', 'reports.show', 'reports.export', 'reports.pdf', 'billing.index', 'profile.edit',
    ];

    /**
     * A-196: panggilan Livewire yang aman di mode hanya-baca — ubah properti
     * (cari, filter) dan pindah halaman. Aksi komponen lain (simpan, kirim, …)
     * tetap ditolak.
     */
    private const LIVEWIRE_READ_CALLS = [
        '$refresh', '$commit', '$set', '$sync', 'gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Company|null $company */
        $company = tenant();

        if ($company === null) {
            return $next($request);
        }

        if ($company->status === CompanyStatus::Provisioning) {
            abort(503, __('Company sedang disiapkan. Coba beberapa saat lagi.'));
        }

        $status = $this->effectiveStatus($company);
        $routeName = $request->route()?->getName();
        $isAuthRoute = in_array($routeName, self::AUTH_ROUTES, true);

        $request->attributes->set('subscription_status', $status);

        if ($isAuthRoute) {
            return $next($request);
        }

        if ($request->hasSession() && $request->session()->has(StartSupportSession::SESSION_KEY)) {
            $this->enforceSupportSession($request, $company, $routeName);
        }

        if ($status === SubscriptionStatus::Terminated) {
            return $this->handleTerminated($request, $next, $routeName);
        }

        if ($status === SubscriptionStatus::Suspended && ! $this->isReadOnly($request, $routeName)) {
            abort(403, __('Langganan ditangguhkan: perubahan data tidak diizinkan.'));
        }

        return $next($request);
    }

    private function handleTerminated(Request $request, Closure $next, ?string $routeName): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRoleCode('company_admin')) {
            abort(403, __('Langganan sudah diakhiri.'));
        }

        $livewireBaca = $this->isLivewireReadOnly($request, $routeName);

        if (! $request->isMethodSafe() && ! $livewireBaca) {
            abort(403, __('Langganan sudah diakhiri: hanya ekspor data yang diizinkan.'));
        }

        if (! $livewireBaca && ! in_array($routeName, self::READ_ALLOWED_WHEN_TERMINATED, true)) {
            abort(403, __('Langganan sudah diakhiri: hanya ekspor data (Laporan) yang diizinkan.'));
        }

        return $next($request);
    }

    /** A-180: sesi dukungan hanya-baca dan gugur saat izin berakhir atau dicabut. */
    private function enforceSupportSession(Request $request, Company $company, ?string $routeName): void
    {
        $akses = SupportAccess::query()->whereKey((int) $request->session()->get(StartSupportSession::SESSION_KEY))
            ->where('company_id', $company->getTenantKey())->first();

        if ($akses === null || ! $akses->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, __('Akses dukungan sudah berakhir atau dicabut.'));
        }

        if (! $request->isMethodSafe() && $routeName !== 'logout' && ! $this->isLivewireReadOnly($request, $routeName)) {
            abort(403, __('Mode akses dukungan: hanya-baca.'));
        }

        $request->attributes->set('support_access', $akses);
    }

    private function isReadOnly(Request $request, ?string $routeName): bool
    {
        if (in_array($routeName, self::WRITE_ALLOWED_WHEN_SUSPENDED, true)) {
            return true;
        }

        return $request->isMethodSafe() || $this->isLivewireReadOnly($request, $routeName);
    }

    /** Update Livewire yang hanya mengubah properti atau halaman (A-196). */
    private function isLivewireReadOnly(Request $request, ?string $routeName): bool
    {
        if ($routeName === null || ! str_ends_with($routeName, 'livewire.update')) {
            return false;
        }

        $komponen = $request->input('components');

        if (! is_array($komponen) || $komponen === []) {
            return false;
        }

        foreach ($komponen as $k) {
            foreach ((array) ($k['calls'] ?? []) as $panggilan) {
                if (! in_array($panggilan['method'] ?? null, self::LIVEWIRE_READ_CALLS, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function statusOf(Company $company): SubscriptionStatus
    {
        $subscription = Subscription::query()
            ->where('company_id', $company->getTenantKey())
            ->latest('id')
            ->first();

        return $subscription?->status ?? SubscriptionStatus::Trial;
    }

    /** Status langganan yang diperberat status company (A-179). */
    public function effectiveStatus(Company $company): SubscriptionStatus
    {
        $status = $this->statusOf($company);

        return match ($company->status) {
            CompanyStatus::Terminated => SubscriptionStatus::Terminated,
            CompanyStatus::Suspended => $status === SubscriptionStatus::Terminated ? $status : SubscriptionStatus::Suspended,
            default => $status,
        };
    }
}
