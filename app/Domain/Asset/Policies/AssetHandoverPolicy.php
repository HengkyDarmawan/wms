<?php

declare(strict_types=1);

namespace App\Domain\Asset\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Models\AssetHandover;

/**
 * Izin serah terima aset (25-aset §2, A-168). Cakupan gudang asal & proyek
 * lewat global scope model (di luar cakupan = 404).
 */
class AssetHandoverPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('asset.view');
    }

    public function view(User $actor, AssetHandover $ast): bool
    {
        return $actor->hasPermission('asset.view');
    }

    /** Melengkapi serah terima keluar: tanggal kembali, meter & grade keluar. */
    public function update(User $actor, AssetHandover $ast): bool
    {
        return $actor->hasPermission('asset.manage')
            && $ast->status === AssetHandoverStatus::CheckedOut
            && $ast->lost_at === null;
    }

    /** Katalog §2.11 `returned → inspected`. */
    public function inspect(User $actor, AssetHandover $ast): bool
    {
        return $actor->hasPermission('asset.inspect')
            && $ast->status === AssetHandoverStatus::Returned;
    }
}
