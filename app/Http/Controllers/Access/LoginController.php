<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\RecordFailedLogin;
use App\Domain\Access\Enums\LoginResult;
use App\Domain\Access\Models\LoginAttempt;
use App\Domain\Access\Models\User;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSubscriptionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login lokal per subdomain company (D-26, Blueprint §13).
 *
 * Back-office `/login` untuk user internal, `/portal/login` untuk user klien
 * (BR-PRJ-07). Kunci akun & catatan percobaan mengikuti NFR-04 dan BR-ACC-06.
 */
class LoginController extends Controller
{
    public function __construct(private readonly RecordFailedLogin $failedLogin) {}

    public function show(): View
    {
        return view('access.auth.login', ['portal' => false]);
    }

    public function showPortal(): View
    {
        return view('access.auth.login', ['portal' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->attempt($request, portal: false);
    }

    public function storePortal(Request $request): RedirectResponse
    {
        return $this->attempt($request, portal: true);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $wasClient = $request->user()?->isClient() ?? false;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($wasClient ? 'portal.login' : 'login');
    }

    private function attempt(Request $request, bool $portal): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'Email',
            'password' => 'Password',
        ]);

        $channel = $portal ? 'portal' : 'web';
        $ip = $request->ip();
        $email = mb_strtolower(trim($credentials['email']));

        /** @var User|null $user */
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->failedLogin->handle(null, $email, $ip, $channel);

            throw $this->invalidCredentials();
        }

        if ($user->isLocked()) {
            LoginAttempt::record($email, LoginResult::Locked, $user->id, $ip, $channel);

            throw ValidationException::withMessages([
                'email' => __('Akun terkunci sampai :waktu. Coba lagi nanti.', [
                    'waktu' => $user->locked_until->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('H:i'),
                ]),
            ]);
        }

        if ($user->password === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->failedLogin->handle($user, $email, $ip, $channel);

            throw $this->invalidCredentials();
        }

        if (! $user->is_active) {
            LoginAttempt::record($email, LoginResult::Inactive, $user->id, $ip, $channel);

            throw ValidationException::withMessages([
                'email' => __('Akun Anda nonaktif. Hubungi Admin Company.'),
            ]);
        }

        // BR-PRJ-07: pintu masuk harus sesuai jenis user.
        if ($user->isClient() !== $portal) {
            LoginAttempt::record($email, LoginResult::WrongPortal, $user->id, $ip, $channel);

            throw ValidationException::withMessages([
                'email' => $portal
                    ? __('Akun internal masuk lewat halaman login utama, bukan portal klien.')
                    : __('Akun klien masuk lewat portal klien di /portal/login.'),
            ]);
        }

        // BR-ACC-01: wajib punya penugasan role yang berlaku.
        if ($user->validAssignments()->isEmpty() && ! $user->hasRoleCode('company_admin')) {
            LoginAttempt::record($email, LoginResult::NoRole, $user->id, $ip, $channel);

            throw ValidationException::withMessages([
                'email' => __('Akun Anda belum punya penugasan role yang berlaku. Hubungi Admin Company.'),
            ]);
        }

        // BR-SUB-03: saat langganan diakhiri, hanya Admin Company yang boleh masuk.
        if ($this->subscriptionTerminated() && ! $user->hasRoleCode('company_admin')) {
            LoginAttempt::record($email, LoginResult::Suspended, $user->id, $ip, $channel);

            throw ValidationException::withMessages([
                'email' => __('Langganan company sudah diakhiri. Hubungi Admin Company.'),
            ]);
        }

        // Verifikasi dua langkah (opsional per user).
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('access.2fa.user_id', $user->id);
            $request->session()->put('access.2fa.remember', $request->boolean('remember'));
            $request->session()->put('access.2fa.portal', $portal);

            return redirect()->route('two-factor');
        }

        return $this->completeLogin($request, $user, $portal, $request->boolean('remember'));
    }

    public function completeLogin(Request $request, User $user, bool $portal, bool $remember): RedirectResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $this->failedLogin->clear($user, $request->ip(), $portal ? 'portal' : 'web');

        return redirect()->intended($portal ? route('portal.dashboard') : route('dashboard'));
    }

    private function subscriptionTerminated(): bool
    {
        $company = tenant();

        if ($company === null) {
            return false;
        }

        return app(EnsureSubscriptionState::class)->statusOf($company) === SubscriptionStatus::Terminated;
    }

    private function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => __('Email atau password salah.'),
        ]);
    }
}
