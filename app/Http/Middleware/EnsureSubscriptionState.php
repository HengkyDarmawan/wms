<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-SUB-02 / BR-SUB-03 (Arsitektur §3.1):
 *  - trial/active : normal
 *  - past_due     : normal + spanduk
 *  - suspended    : hanya permintaan baca; proses masuk & unggah bukti bayar tetap boleh
 *  - terminated   : hanya Admin Company, hanya untuk ekspor (pembatasan siapa yang
 *                   boleh masuk ditegakkan LoginController)
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
    ];

    /** Route yang tetap boleh menulis saat langganan ditangguhkan. */
    private const WRITE_ALLOWED_WHEN_SUSPENDED = [
        'billing.payment.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Company|null $company */
        $company = tenant();

        if ($company === null) {
            return $next($request);
        }

        $status = $this->statusOf($company);
        $routeName = $request->route()?->getName();
        $isAuthRoute = in_array($routeName, self::AUTH_ROUTES, true);

        $request->attributes->set('subscription_status', $status);

        if ($isAuthRoute) {
            return $next($request);
        }

        if ($status === SubscriptionStatus::Terminated) {
            return $this->handleTerminated($request, $next);
        }

        if ($status === SubscriptionStatus::Suspended && ! $this->isReadOnly($request, $routeName)) {
            abort(403, __('Langganan ditangguhkan: perubahan data tidak diizinkan.'));
        }

        return $next($request);
    }

    private function handleTerminated(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRoleCode('company_admin')) {
            abort(403, __('Langganan sudah diakhiri.'));
        }

        if (! $request->isMethodSafe()) {
            abort(403, __('Langganan sudah diakhiri: hanya ekspor data yang diizinkan.'));
        }

        return $next($request);
    }

    private function isReadOnly(Request $request, ?string $routeName): bool
    {
        if (in_array($routeName, self::WRITE_ALLOWED_WHEN_SUSPENDED, true)) {
            return true;
        }

        return $request->isMethodSafe();
    }

    public function statusOf(Company $company): SubscriptionStatus
    {
        $subscription = Subscription::query()
            ->where('company_id', $company->getTenantKey())
            ->latest('id')
            ->first();

        return $subscription?->status ?? SubscriptionStatus::Trial;
    }
}
