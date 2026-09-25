<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\ManageTwoFactor;
use App\Domain\Access\Actions\RecordFailedLogin;
use App\Domain\Access\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Verifikasi dua langkah opsional (Blueprint §13). Sesi hanya menyimpan id user
 * yang sudah lolos password; login baru dibuat setelah kode benar.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly ManageTwoFactor $twoFactor,
        private readonly LoginController $login,
        private readonly RecordFailedLogin $failedLogin,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('access.2fa.user_id')) {
            return redirect()->route('login');
        }

        return view('access.auth.two-factor');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('access.2fa.user_id');

        if ($userId === null) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ], [], ['code' => 'Kode']);

        /** @var User|null $user */
        $user = User::find($userId);

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['access.2fa.user_id', 'access.2fa.remember', 'access.2fa.portal']);

            return redirect()->route('login');
        }

        // Kunci akun berlaku juga di langkah kode (A-205), supaya login ulang
        // tidak menjadi jalan menebak kode tanpa batas.
        if ($user->isLocked()) {
            $request->session()->forget(['access.2fa.user_id', 'access.2fa.remember', 'access.2fa.portal', 'access.2fa.attempts']);

            throw ValidationException::withMessages(['code' => __('Akun terkunci. Coba lagi nanti.')]);
        }

        $attempts = (int) $request->session()->get('access.2fa.attempts', 0) + 1;
        $max = (int) config('access.two_factor.max_attempts', 5);

        if ($attempts >= $max) {
            $request->session()->forget(['access.2fa.user_id', 'access.2fa.remember', 'access.2fa.portal', 'access.2fa.attempts']);

            throw ValidationException::withMessages([
                'code' => __('Terlalu banyak percobaan. Silakan masuk ulang.'),
            ]);
        }

        // TOTP (tidak bisa dipakai ulang, A-205) atau kode pemulihan sekali pakai.
        if (! $this->twoFactor->verifyLogin($user, $data['code'])) {
            $this->failedLogin->handle($user, $user->email, $request->ip());
            $request->session()->put('access.2fa.attempts', $attempts);

            throw ValidationException::withMessages([
                'code' => __('Kode verifikasi tidak cocok.'),
            ]);
        }

        $portal = (bool) $request->session()->get('access.2fa.portal', false);
        $remember = (bool) $request->session()->get('access.2fa.remember', false);

        $request->session()->forget(['access.2fa.user_id', 'access.2fa.remember', 'access.2fa.portal', 'access.2fa.attempts']);

        return $this->login->completeLogin($request, $user, $portal, $remember);
    }
}
