<?php

declare(strict_types=1);

namespace App\Domain\Master\Policies;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Berlaku untuk {@see \App\Domain\Master\Models\Uom} dan
 * {@see \App\Domain\Master\Models\UomCategory}: satu permission untuk keduanya
 * karena satuan tidak berguna tanpa kategorinya (11-master §2).
 */
class UomPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('uom.view');
    }

    public function view(User $actor, Model $model): bool
    {
        return $actor->hasPermission('uom.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('uom.manage');
    }

    public function update(User $actor, Model $model): bool
    {
        return $actor->hasPermission('uom.manage');
    }

    public function deactivate(User $actor, Model $model): bool
    {
        return $actor->hasPermission('uom.manage');
    }
}
