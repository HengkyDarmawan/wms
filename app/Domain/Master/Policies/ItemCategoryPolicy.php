<?php

declare(strict_types=1);

namespace App\Domain\Master\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\ItemCategory;

class ItemCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('item_category.view');
    }

    public function view(User $actor, ItemCategory $category): bool
    {
        return $actor->hasPermission('item_category.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('item_category.manage');
    }

    public function update(User $actor, ItemCategory $category): bool
    {
        return $actor->hasPermission('item_category.manage');
    }

    public function deactivate(User $actor, ItemCategory $category): bool
    {
        return $actor->hasPermission('item_category.manage');
    }
}
