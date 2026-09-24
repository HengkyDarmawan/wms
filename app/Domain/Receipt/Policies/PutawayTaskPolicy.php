<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Models\PutawayTask;

/** Izin tugas put-away (19-receipt-putaway §2); cakupan gudang lewat global scope. */
class PutawayTaskPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('putaway.view');
    }

    public function view(User $actor, PutawayTask $task): bool
    {
        return $actor->hasPermission('putaway.view');
    }

    public function complete(User $actor, PutawayTask $task): bool
    {
        return $actor->hasPermission('putaway.complete') && $task->status === PutawayTaskStatus::Pending;
    }

    public function cancel(User $actor, PutawayTask $task): bool
    {
        return $actor->hasPermission('putaway.cancel') && $task->status === PutawayTaskStatus::Pending;
    }
}
