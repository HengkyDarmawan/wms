<?php

declare(strict_types=1);

namespace App\Domain\Approval\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Support\ApprovalRegistry;

/**
 * Izin tugas approval: hanya approver yang ditugaskan, memegang permission
 * approve jenis dokumennya (A-86), dan bukan pengaju (BR-APR-03).
 */
class ApprovalTaskPolicy
{
    public function __construct(private readonly ApprovalRegistry $registry) {}

    public function decide(User $actor, ApprovalTask $task): bool
    {
        $task->loadMissing('snapshot');
        $snapshot = $task->snapshot;

        return $task->status === ApprovalTaskStatus::Open
            && $snapshot !== null
            && $snapshot->status === ApprovalSnapshotStatus::Pending
            && (int) $task->approver_user_id === (int) $actor->id
            && ! in_array((int) $actor->id, $snapshot->requesterIds(), true)
            && $this->registry->has($snapshot->document_type)
            && $actor->hasPermission($this->registry->handler($snapshot->document_type)->approvePermission());
    }

    public function escalate(User $actor, ApprovalTask $task): bool
    {
        return $actor->hasPermission('approval.escalate') && $task->status === ApprovalTaskStatus::Open;
    }
}
