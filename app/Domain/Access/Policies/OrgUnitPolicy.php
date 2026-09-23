<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\User;

class OrgUnitPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('org.view');
    }

    public function view(User $actor, OrgUnit $unit): bool
    {
        return $actor->hasPermission('org.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('org.manage');
    }

    public function update(User $actor, OrgUnit $unit): bool
    {
        return $actor->hasPermission('org.manage');
    }
}
