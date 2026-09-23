<?php

declare(strict_types=1);

namespace App\Domain\Master\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;

class ItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('item.view');
    }

    public function view(User $actor, Item $item): bool
    {
        return $actor->hasPermission('item.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('item.create');
    }

    public function update(User $actor, Item $item): bool
    {
        return $actor->hasPermission('item.update');
    }

    public function deactivate(User $actor, Item $item): bool
    {
        return $actor->hasPermission('item.deactivate');
    }
}
