<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('role.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('role.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('role.create');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->hasPermission('role.update');
    }

    /** Role bawaan tidak bisa dinonaktifkan/dihapus (10-access §6.4, P-03). */
    public function deactivate(User $actor, Role $role): bool
    {
        return ! $role->is_builtin && $actor->hasPermission('role.deactivate');
    }
}
