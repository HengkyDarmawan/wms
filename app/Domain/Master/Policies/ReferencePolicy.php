<?php

declare(strict_types=1);

namespace App\Domain\Master\Policies;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Data referensi kecil: alasan baku, kategori penyimpanan, kendaraan, dan
 * ekspedisi. Semuanya memakai `reference.view` / `reference.manage`
 * (11-master §2) supaya layar Referensi cukup satu izin.
 */
class ReferencePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('reference.view');
    }

    public function view(User $actor, Model $model): bool
    {
        return $actor->hasPermission('reference.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('reference.manage');
    }

    public function update(User $actor, Model $model): bool
    {
        return $actor->hasPermission('reference.manage');
    }

    public function deactivate(User $actor, Model $model): bool
    {
        return $actor->hasPermission('reference.manage');
    }
}
