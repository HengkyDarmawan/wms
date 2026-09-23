<?php

declare(strict_types=1);

namespace App\Domain\Master\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;

class ProjectPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('project.view');
    }

    /**
     * BR-ACC-05: user yang cakupannya dibatasi proyek tertentu hanya melihat
     * proyek itu; cakupan `all` dan cakupan gudang melihat semuanya.
     */
    public function view(User $actor, Project $project): bool
    {
        if (! $actor->hasPermission('project.view')) {
            return false;
        }

        // null berarti tidak dibatasi proyek (cakupan `all`, atau user memang
        // hanya dibatasi gudang). Lihat User::accessibleScopeIds().
        return $actor->canAccessProject((int) $project->id);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('project.create');
    }

    public function update(User $actor, Project $project): bool
    {
        return $actor->hasPermission('project.update');
    }

    /** Menutup, membatalkan, atau mengarsipkan proyek (§4). */
    public function close(User $actor, Project $project): bool
    {
        return $actor->hasPermission('project.close');
    }
}
