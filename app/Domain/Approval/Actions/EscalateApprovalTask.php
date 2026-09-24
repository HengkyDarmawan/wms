<?php

declare(strict_types=1);

namespace App\Domain\Approval\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Support\ApprovalEngine;

/**
 * Permission: `approval.escalate` — mengalihkan tugas approval yang macet
 * secara manual (BR-APR-06, A-90). Jalur otomatisnya perintah
 * `approval:escalate` yang dijadwalkan tiap jam (BR-APR-08).
 */
class EscalateApprovalTask
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    public function handle(ApprovalTask $task, User $actor): ApprovalTask
    {
        if (! $actor->hasPermission('approval.escalate')) {
            throw ApprovalRuleException::rule('BR-GEN-09', 'Anda tidak memegang izin approval.escalate.');
        }

        return $this->engine->escalate($task, 'manual', $actor);
    }

    /**
     * Jalur penjadwal (tanpa pelaku).
     *
     * @return array{escalated: int, skipped: int, delegated: int}
     */
    public function runScheduled(): array
    {
        return $this->engine->runScheduled();
    }
}
