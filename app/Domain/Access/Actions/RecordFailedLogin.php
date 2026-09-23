<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\LoginResult;
use App\Domain\Access\Models\LoginAttempt;
use App\Domain\Access\Models\User;
use App\Domain\Access\Notifications\AccountLockedNotification;

/**
 * NFR-04 / BR-ACC-06: N gagal berturut-turut mengunci akun selama M menit.
 */
class RecordFailedLogin
{
    public function handle(?User $user, string $email, ?string $ip = null, string $channel = 'web'): void
    {
        if ($user === null) {
            LoginAttempt::record($email, LoginResult::Invalid, null, $ip, $channel);

            return;
        }

        $max = (int) config('access.login.max_attempts', 5);
        $minutes = (int) config('access.login.lock_minutes', 15);

        $count = $user->failed_login_count + 1;
        $lockedNow = false;

        $attributes = ['failed_login_count' => $count];

        if ($count >= $max) {
            $attributes['locked_until'] = now()->addMinutes($minutes);
            $attributes['failed_login_count'] = 0;
            $lockedNow = true;
        }

        $user->forceFill($attributes)->save();

        LoginAttempt::record(
            $email,
            $lockedNow ? LoginResult::Locked : LoginResult::Invalid,
            $user->id,
            $ip,
            $channel,
        );

        if ($lockedNow) {
            $user->notify(new AccountLockedNotification($minutes));

            activity('access')
                ->performedOn($user)
                ->withProperties(['minutes' => $minutes, 'ip' => $ip])
                ->log('Akun terkunci karena gagal login berulang');
        }
    }

    /** Dipanggil setelah login berhasil. */
    public function clear(User $user, ?string $ip = null, string $channel = 'web'): void
    {
        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        LoginAttempt::record($user->email, LoginResult::Success, $user->id, $ip, $channel);
    }
}
