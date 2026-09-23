<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\PlatformUser;
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

        $user = PlatformUser::query()->where('email', $data['email'])->first();

        // Pesan sengaja sama untuk email tidak dikenal maupun password salah,
        // supaya keberadaan akun tidak bocor (NFR-02).
        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Email atau password salah.'),
            ]);
        }

        Auth::guard('platform')->login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('platform.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
