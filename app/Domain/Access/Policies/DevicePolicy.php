<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Domain\Access\Models\Device;
use App\Domain\Access\Models\User;

class DevicePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('device.view') || $actor->hasPermission('device.manage');
    }

    /** User selalu boleh melihat & mencabut perangkatnya sendiri. */
    public function view(User $actor, Device $device): bool
    {
        return $device->user_id === $actor->id || $actor->hasPermission('device.view');
    }

    /**
     * Perangkat sendiri selalu boleh dicabut. Mencabut perangkat user lain butuh
     * `device.manage` **dan** `device.view` — 10-access §2: `device.manage` saja
     * berarti "perangkat sendiri" (Staf Gudang, Driver).
     */
    public function revoke(User $actor, Device $device): bool
    {
        if ($device->user_id === $actor->id) {
            return true;
        }

        return $actor->hasPermission('device.manage') && $actor->hasPermission('device.view');
    }
}
