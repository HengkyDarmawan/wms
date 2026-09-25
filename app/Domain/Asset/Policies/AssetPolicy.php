<?php

declare(strict_types=1);

namespace App\Domain\Asset\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Serial;

/**
 * Izin daftar & detail aset (serial item aset) — 25-aset §2, A-168.
 */
class AssetPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('asset.view');
    }

    public function view(User $actor, Serial $serial): bool
    {
        return $actor->hasPermission('asset.view') && (bool) $serial->item?->isAsset();
    }

    /** Profil masa pakai (A-66). */
    public function update(User $actor, Serial $serial): bool
    {
        return $actor->hasPermission('asset.manage')
            && ! in_array($serial->asset_state, [AssetState::Lost, AssetState::WrittenOff], true);
    }

    /** BR-AST-04. */
    public function markLost(User $actor, Serial $serial): bool
    {
        return $actor->hasPermission('asset.mark_lost')
            && ! in_array($serial->asset_state, [AssetState::Lost, AssetState::WrittenOff, AssetState::InTransit], true);
    }

    public function found(User $actor, Serial $serial): bool
    {
        return $actor->hasPermission('asset.mark_lost') && $serial->asset_state === AssetState::Lost;
    }
}
