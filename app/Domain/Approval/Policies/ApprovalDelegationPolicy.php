<?php

declare(strict_types=1);

namespace App\Domain\Approval\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Models\ApprovalDelegation;

/** Izin delegasi approval (BR-APR-05, A-89). */
class ApprovalDelegationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('approval.delegate') || $actor->hasPermission('approval_rule.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('approval.delegate') || $actor->hasPermission('approval_rule.manage');
    }

    public function end(User $actor, ApprovalDelegation $delegation): bool
    {
        return $delegation->is_active && (
            ((int) $delegation->from_user_id === (int) $actor->id && $actor->hasPermission('approval.delegate'))
            || $actor->hasPermission('approval_rule.manage')
        );
    }
}
