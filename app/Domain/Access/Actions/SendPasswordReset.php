<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Permission: `user.reset_password`.
 *
 * Admin mengirim tautan atur ulang password; admin tidak pernah menetapkan
 * password milik user (Blueprint §13).
 */
class SendPasswordReset
{
    public function handle(User $user, ?User $actor = null): void
    {
        if (! $user->is_active) {
            throw new AccessRuleException('User nonaktif tidak bisa dikirimi tautan password.');
        }

        Password::sendResetLink(['email' => $user->email]);

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->log('Tautan atur ulang password dikirim');
    }
}
