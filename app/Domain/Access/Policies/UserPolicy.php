<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Domain\Access\Models\User;

/**
 * Gate::before (AccessServiceProvider) sudah meluluskan user yang punya permission.
 * Policy ini menambahkan batas cakupan dan aturan modul (BR-ACC-02, BR-ACC-03).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('user.view');
    }

    public function view(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return true;
        }

        return $actor->hasPermission('user.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('user.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('user.update');
    }

    /** BR-ACC-02 diperiksa lagi di Action; di sini hanya hak aksesnya. */
    public function deactivate(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false; // tidak menonaktifkan diri sendiri
        }

        return $actor->hasPermission('user.deactivate');
    }

    public function invite(User $actor): bool
    {
        return $actor->hasPermission('user.invite');
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->hasPermission('user.reset_password');
    }

    public function assignRole(User $actor): bool
    {
        return $actor->hasPermission('role.assign');
    }
}
