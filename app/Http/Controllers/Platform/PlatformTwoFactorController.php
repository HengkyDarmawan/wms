<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Access\Actions\ManageTwoFactor;
use App\Domain\Access\Enums\LoginResult;
use App\Domain\Platform\Models\PlatformLoginAttempt;
use App\Domain\Platform\Models\PlatformUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 2FA Super Admin (A-200, NFR-04): langkah kode saat masuk dan layar
 * *Keamanan akun*. Pengaturan (mulai, konfirmasi, batal, matikan, ganti kode
 * pemulihan) memakai `Access\TwoFactorSetupController` yang sama dengan
 * tenant — `$request->user()` di grup `auth:platform` adalah Super Admin.
 */
class PlatformTwoFactorController extends Controller
{
    public function __construct(private readonly ManageTwoFactor $twoFactor) {}

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('platform.2fa.user_id')) {
            return redirect()->route('platform.login');
        }

        return view('platform.auth.two-factor');
    }

    public function verify(Request $request, PlatformLoginController $login): RedirectResponse
    {
        $tunda = $request->session()->get('platform.2fa');

        if (! is_array($tunda) || ! isset($tunda['user_id'])) {
            return redirect()->route('platform.login');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:40']], attributes: ['code' => __('Kode')]);
        $user = PlatformUser::query()->find($tunda['user_id']);

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('platform.2fa');

            return redirect()->route('platform.login');
        }

        // Kunci akun berlaku juga di langkah kode, supaya login ulang tidak
        // menjadi jalan menebak kode tanpa batas.
        if ($user->isLocked()) {
            $request->session()->forget('platform.2fa');
            PlatformLoginAttempt::record($user->email, LoginResult::Locked, $user->id, $request->ip());

            throw ValidationException::withMessages(['code' => __('Akun terkunci. Coba lagi nanti.')]);
        }

        if (! $this->twoFactor->verifyLogin($user, $data['code'])) {
            $percobaan = (int) ($tunda['attempts'] ?? 0) + 1;
            $login->gagal($user, $user->email, $request->ip());

            if ($percobaan >= (int) config('access.two_factor.max_attempts', 5)) {
                $request->session()->forget('platform.2fa');

                throw ValidationException::withMessages(['code' => __('Terlalu banyak percobaan. Silakan masuk ulang.')]);
            }

            $request->session()->put('platform.2fa.attempts', $percobaan);

            throw ValidationException::withMessages(['code' => __('Kode verifikasi tidak cocok.')]);
        }

        $request->session()->forget('platform.2fa');

        return $login->completeLogin($request, $user, (bool) ($tunda['remember'] ?? false));
    }

    public function security(Request $request): View
    {
        /** @var PlatformUser $user */
        $user = $request->user('platform');

        return view('platform.security', [
            'user' => $user,
            'sisaKodePemulihan' => $this->twoFactor->remainingRecoveryCodes($user),
        ]);
    }
}
