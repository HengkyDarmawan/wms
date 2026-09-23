<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\PasswordHistory;
use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * BR-ACC-06: panjang minimum, tidak boleh sama dengan beberapa password terakhir,
 * dan mengganti password menghapus sesi lain milik user.
 */
class ChangePassword
{
    public function handle(
        User $user,
        string $newPassword,
        bool $requireCurrent = true,
        ?string $currentPassword = null,
        bool $revokeOtherSessions = true,
    ): User {
        $this->guardLength($newPassword);

        if ($requireCurrent) {
            $this->guardCurrent($user, $currentPassword);
        }

        $this->guardHistory($user, $newPassword);

        DB::transaction(function () use ($user, $newPassword, $revokeOtherSessions): void {
            $hashed = Hash::make($newPassword);

            $user->forceFill([
                'password' => $hashed,
                'password_changed_at' => now(),
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            PasswordHistory::create([
                'user_id' => $user->id,
                'password' => $hashed,
                'created_at' => now(),
            ]);

            $this->trimHistory($user);

            if ($revokeOtherSessions) {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', session()->getId())
                    ->delete();
            }
        });

        activity('access')->performedOn($user)->log('Password diganti');

        return $user->refresh();
    }

    private function guardLength(string $password): void
    {
        $min = (int) config('access.password.min_length', 10);

        if (mb_strlen($password) < $min) {
            throw AccessRuleException::rule(
                'BR-ACC-06',
                'Password minimal '.$min.' karakter.',
            );
        }
    }

    private function guardCurrent(User $user, ?string $currentPassword): void
    {
        if ($currentPassword === null || $user->password === null
            || ! Hash::check($currentPassword, $user->password)) {
            throw new AccessRuleException('Password lama tidak cocok.');
        }
    }

    /** Password baru tidak boleh sama dengan N password terakhir. */
    private function guardHistory(User $user, string $newPassword): void
    {
        $keep = (int) config('access.password.history', 3);

        if ($keep < 1) {
            return;
        }

        $recent = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($keep)
            ->pluck('password');

        foreach ($recent as $hash) {
            if (Hash::check($newPassword, $hash)) {
                throw AccessRuleException::rule(
                    'BR-ACC-06',
                    'Password tidak boleh sama dengan '.$keep.' password terakhir.',
                );
            }
        }
    }

    private function trimHistory(User $user): void
    {
        $keep = (int) config('access.password.history', 3);

        $ids = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($keep)
            ->pluck('id');

        PasswordHistory::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $ids)
            ->delete();
    }
}
