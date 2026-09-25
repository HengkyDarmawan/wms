<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Access\Enums\LoginResult;
use App\Domain\Platform\Models\PlatformLoginAttempt;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Support\PlatformAudit;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login Super Admin platform di domain pusat (Blueprint §4.1, §14).
 *
 * Guard `platform` dan tabel `platform_users` sudah ada sejak modul Access,
 * tetapi tidak pernah punya route masuk, sehingga akun yang dibuat seeder
 * tidak bisa dipakai sama sekali. Ini menutup lubang itu.
 *
 * Super Admin TIDAK bisa membuka data operasional company dari sini; itu hanya
 * mungkin lewat akses dukungan berperiode (A-27, BR-SUB-04).
 */
class PlatformLoginController extends Controller
{
    public function show(): View
    {
        return view('platform.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], attributes: [
            'email' => __('Email'),
            'password' => __('Password'),
        ]);

        $email = mb_strtolower(trim($data['email']));
        $ip = $request->ip();
        $user = PlatformUser::query()->where('email', $email)->first();

        // NFR-04 / A-182: penguncian sama dengan login tenant (config/access.php).
        if ($user !== null && $user->isLocked()) {
            PlatformLoginAttempt::record($email, LoginResult::Locked, $user->id, $ip);

            throw ValidationException::withMessages([
                'email' => __('Akun terkunci sampai :waktu. Coba lagi nanti.', [
                    'waktu' => $user->locked_until->timezone('Asia/Jakarta')->format('H:i'),
                ]),
            ]);
        }

        // Pesan sengaja sama untuk email tidak dikenal maupun password salah,
        // supaya keberadaan akun tidak bocor.
        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            $this->gagal($user, $email, $ip);

            throw ValidationException::withMessages([
                'email' => __('Email atau password salah.'),
            ]);
        }

        // A-200: 2FA aktif → sesi hanya menyimpan id yang lolos password; masuk setelah kode benar.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('platform.2fa', ['user_id' => $user->id, 'remember' => $request->boolean('remember'), 'attempts' => 0]);

            return redirect()->route('platform.two-factor');
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    public function completeLogin(Request $request, PlatformUser $user, bool $remember): RedirectResponse
    {
        Auth::guard('platform')->login($user, $remember);

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'failed_login_count' => 0, 'locked_until' => null])->save();
        PlatformLoginAttempt::record($user->email, LoginResult::Success, $user->id, $request->ip());

        return redirect()->intended(route('platform.dashboard'));
    }

    /** Juga dipakai langkah kode 2FA: kode salah menambah hitungan gagal & bisa mengunci akun (A-200). */
    public function gagal(?PlatformUser $user, string $email, ?string $ip): void
    {
        if ($user === null) {
            PlatformLoginAttempt::record($email, LoginResult::Invalid, null, $ip);

            return;
        }

        $jumlah = $user->failed_login_count + 1;
        $kunci = $jumlah >= (int) config('access.login.max_attempts', 5);

        $user->forceFill([
            'failed_login_count' => $kunci ? 0 : $jumlah,
            'locked_until' => $kunci ? now()->addMinutes((int) config('access.login.lock_minutes', 15)) : $user->locked_until,
        ])->save();

        PlatformLoginAttempt::record($email, $kunci ? LoginResult::Locked : LoginResult::Invalid, $user->id, $ip);

        if ($kunci) {
            PlatformAudit::record('Akun Super Admin terkunci karena gagal login berulang', $user, null, ['ip' => $ip]);
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
