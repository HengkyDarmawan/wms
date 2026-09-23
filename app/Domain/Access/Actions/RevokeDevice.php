<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Device;
use App\Domain\Access\Models\User;

/**
 * Permission: `device.manage` (atau perangkat milik sendiri).
 *
 * P-03: perangkat tidak dihapus, hanya dicabut, agar jejak sinkron PWA dan
 * bukti terima yang pernah dibuat dari perangkat itu tetap terbaca.
 */
class RevokeDevice
{
    public function handle(Device $device, ?User $actor = null): Device
    {
        $device->forceFill(['is_active' => false])->save();

        activity('access')
            ->performedOn($device->user)
            ->causedBy($actor)
            ->withProperties(['device_uid' => $device->device_uid, 'name' => $device->name])
            ->log('Perangkat dicabut');

        return $device->refresh();
    }

    public function reactivate(Device $device, ?User $actor = null): Device
    {
        $device->forceFill(['is_active' => true])->save();

        activity('access')
            ->performedOn($device->user)
            ->causedBy($actor)
            ->withProperties(['device_uid' => $device->device_uid])
            ->log('Perangkat diaktifkan kembali');

        return $device->refresh();
    }
}
