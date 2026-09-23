<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\AcceptInvitation;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman undangan: user baru mengatur password pertama
 * (template `auth/verify-email.html`, 10-access §6.1).
 */
class InvitationController extends Controller
{
    public function __construct(private readonly AcceptInvitation $acceptInvitation) {}

    public function show(string $token): View
    {
        $invitation = AcceptInvitation::findUsable($token);

        if ($invitation === null || $invitation->isExpired()) {
            return view('access.auth.invitation-expired');
        }

        return view('access.auth.invitation', [
            'token' => $token,
            'user' => $invitation->user,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:'.config('access.password.min_length', 10), 'confirmed'],
        ], [], [
            'name' => 'Nama',
            'phone' => 'Nomor WhatsApp',
            'password' => 'Password',
        ]);

        try {
            $this->acceptInvitation->handle(
                $token,
                $data['password'],
                $data['name'] ?? null,
                $data['phone'] ?? null,
            );
        } catch (AccessRuleException $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        return redirect()->route('login')
            ->with('status', __('Password berhasil diatur. Silakan masuk.'));
    }
}
