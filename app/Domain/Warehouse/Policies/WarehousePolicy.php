<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('warehouse.view');
    }

    /** BR-ACC-05: cakupan gudang membatasi gudang mana yang boleh dibuka. */
    public function view(User $actor, Warehouse $warehouse): bool
    {
        return $actor->hasPermission('warehouse.view')
            && $actor->canAccessWarehouse((int) $warehouse->id);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('warehouse.create');
    }

    public function update(User $actor, Warehouse $warehouse): bool
    {
        return $actor->hasPermission('warehouse.update')
            && $actor->canAccessWarehouse((int) $warehouse->id);
    }

    /** Denah gudang (A-254): ubah ukuran/posisi, rak area, bin ikut terpakai. */
    public function manageLayout(User $actor, Warehouse $warehouse): bool
    {
        return $actor->hasPermission('bin.manage')
            && $actor->canAccessWarehouse((int) $warehouse->id);
    }

    public function deactivate(User $actor, Warehouse $warehouse): bool
    {
        return $actor->hasPermission('warehouse.deactivate');
    }
}
