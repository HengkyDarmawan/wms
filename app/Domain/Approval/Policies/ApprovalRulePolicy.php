<?php

declare(strict_types=1);

namespace App\Domain\Approval\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Models\ApprovalRule;

/** Izin aturan approval (20-approval §2). */
class ApprovalRulePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('approval_rule.view') || $actor->hasPermission('approval_rule.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('approval_rule.manage');
    }

    public function update(User $actor, ApprovalRule $rule): bool
    {
        return $actor->hasPermission('approval_rule.manage');
    }
}
