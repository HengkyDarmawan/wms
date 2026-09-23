<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;

/**
 * Permission: `role.deactivate`.
 *
 * P-03: role tidak dihapus, hanya dinonaktifkan. Role bawaan tidak bisa
 * dinonaktifkan, dan role yang masih dipakai harus dicabut dulu penugasannya.
 */
class DeactivateRole
{
    public function handle(Role $role, ?User $actor = null): Role
    {
        if ($role->is_builtin) {
            throw new AccessRuleException('Role bawaan tidak bisa dinonaktifkan.');
        }

        $dipakai = $role->assignments()->count();

        if ($dipakai > 0) {
            throw new AccessRuleException(
                'Role masih dipakai '.$dipakai.' penugasan. Cabut penugasannya lebih dulu.',
            );
        }

        $role->forceFill(['is_active' => false])->save();

        activity('access')->performedOn($role)->causedBy($actor)->log('Role dinonaktifkan');

        return $role->refresh();
    }

    public function reactivate(Role $role, ?User $actor = null): Role
    {
        $role->forceFill(['is_active' => true])->save();

        activity('access')->performedOn($role)->causedBy($actor)->log('Role diaktifkan kembali');

        return $role->refresh();
    }
}
