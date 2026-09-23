<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Models\Bin;

class BinPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('bin.view');
    }

    public function view(User $actor, Bin $bin): bool
    {
        return $actor->hasPermission('bin.view')
            && $actor->canAccessWarehouse((int) $bin->warehouse_id);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('bin.manage');
    }

    public function update(User $actor, Bin $bin): bool
    {
        return $actor->hasPermission('bin.manage')
            && $actor->canAccessWarehouse((int) $bin->warehouse_id);
    }

    /** Membekukan, mencairkan, menonaktifkan (12-warehouse §4). */
    public function manage(User $actor, Bin $bin): bool
    {
        return $this->update($actor, $bin);
    }

    public function deactivate(User $actor, Bin $bin): bool
    {
        return $this->update($actor, $bin);
    }
}
