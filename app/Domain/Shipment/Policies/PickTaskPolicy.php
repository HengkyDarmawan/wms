<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Models\PickTask;

/**
 * Izin tugas picking (15-picking-shipment §2).
 *
 * Cakupan gudangnya sudah ditegakkan global scope; di sini yang diperiksa
 * adalah izin dan kelayakan status, supaya tombol yang tidak berguna tidak
 * muncul sama sekali.
 */
class PickTaskPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('pick.view');
    }

    public function view(User $actor, PickTask $task): bool
    {
        return $actor->hasPermission('pick.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('pick.create');
    }

    public function start(User $actor, PickTask $task): bool
    {
        return $actor->hasPermission('pick.start') && $task->status->value === 'pending';
    }

    public function complete(User $actor, PickTask $task): bool
    {
        return $actor->hasPermission('pick.complete') && $task->status->value === 'in_progress';
    }

    public function cancel(User $actor, PickTask $task): bool
    {
        return $actor->hasPermission('pick.cancel') && ! $task->status->isFinal();
    }
}
