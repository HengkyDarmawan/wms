<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\CompanyAdminGuard;

/**
 * Permission: `role.assign`.
 * BR-ACC-02: penugasan Admin Company aktif terakhir tidak boleh dicabut.
 */
class RevokeRole
{
    public function __construct(private readonly CompanyAdminGuard $adminGuard) {}

    public function handle(RoleAssignment $assignment, ?User $actor = null): void
    {
        $user = $assignment->user;
        $role = $assignment->role;

        if ($role !== null && $role->code === CompanyAdminGuard::ROLE_CODE) {
            $this->adminGuard->ensureNotLastAdmin($user, 'mencabut role Admin Company');
        }

        $assignment->delete();
        $user?->forgetPermissionCache();

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties(['role' => $role?->code])
            ->log('Penugasan role dicabut');
    }
}
