<?php

declare(strict_types=1);

namespace App\Domain\Count\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Count\Models\CountAssignment;

/**
 * Halaman hitung (mobile): hanya penghitung yang ditugaskan, pemegang
 * `count.record` (A-95). Penugasan yang sudah selesai boleh dibuka lagi
 * untuk dilihat, tetapi tidak diisi.
 */
class CountAssignmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('count.record');
    }

    public function view(User $actor, CountAssignment $assignment): bool
    {
        return $actor->hasPermission('count.record') && (int) $assignment->counter_user_id === (int) $actor->id;
    }

    public function record(User $actor, CountAssignment $assignment): bool
    {
        return $this->view($actor, $assignment)
            && ! $assignment->isDone()
            && $assignment->stockCount?->status->isCounting() === true;
    }
}
