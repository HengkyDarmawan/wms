<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Models\WarehouseType;

class WarehouseTypePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('warehouse_type.view');
    }

    public function view(User $actor, WarehouseType $type): bool
    {
        return $actor->hasPermission('warehouse_type.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('warehouse_type.manage');
    }

    public function update(User $actor, WarehouseType $type): bool
    {
        return $actor->hasPermission('warehouse_type.manage');
    }

    /** P-03: tipe bawaan tidak bisa dinonaktifkan. */
    public function deactivate(User $actor, WarehouseType $type): bool
    {
        return ! $type->is_builtin && $actor->hasPermission('warehouse_type.manage');
    }
}
