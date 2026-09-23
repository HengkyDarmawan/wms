<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;

/**
 * Permission: `user.deactivate` (aksi kebalikannya).
 * BR-ACC-01: user yang diaktifkan kembali harus punya penugasan role yang berlaku.
 */
class ReactivateUser
{
    public function handle(User $user, ?User $actor = null): User
    {
        if ($user->roleAssignments()->valid()->count() === 0) {
            throw AccessRuleException::rule(
                'BR-ACC-01',
                'Beri penugasan role yang berlaku sebelum mengaktifkan user ini.',
            );
        }

        $user->forceFill([
            'is_active' => true,
            'locked_until' => null,
            'failed_login_count' => 0,
        ])->save();

        activity('access')->performedOn($user)->causedBy($actor)->log('User diaktifkan kembali');

        return $user->refresh();
    }
}
