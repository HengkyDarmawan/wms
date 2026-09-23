<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\ChangePassword;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Lupa & atur ulang password (10-access §6.1). Balasan selalu sama agar tidak
 * membocorkan email yang terdaftar.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly ChangePassword $changePassword) {}

    public function request(): View
    {
        return view('access.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ], [], ['email' => 'Email']);

        Password::sendResetLink(['email' => $data['email']]);

        return back()->with('status', __('Bila email terdaftar, tautan atur ulang password sudah dikirim.'));
    }

    public function reset(string $token): View
    {
        return view('access.auth.reset-password', ['token' => $token]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:'.config('access.password.min_length', 10), 'confirmed'],
        ], [], [
            'email' => 'Email',
            'password' => 'Password',
        ]);

        $ruleError = null;

        $status = Password::reset($data, function (User $user, string $password) use (&$ruleError): void {
            try {
                $this->changePassword->handle($user, $password, requireCurrent: false);
                event(new PasswordReset($user));
            } catch (AccessRuleException $e) {
                $ruleError = $e->getMessage();
            }
        });

        if ($ruleError !== null) {
            throw ValidationException::withMessages(['password' => $ruleError]);
        }

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => __('Tautan atur ulang password tidak berlaku atau sudah kedaluwarsa.'),
            ]);
        }

        return redirect()->route('login')->with('status', __('Password berhasil diubah. Silakan masuk.'));
    }
}
