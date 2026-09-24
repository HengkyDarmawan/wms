<?php

declare(strict_types=1);

namespace App\Domain\Approval\Models;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalChannel;
use App\Domain\Approval\Enums\ApprovalDecisionType;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keputusan pada satu tugas: setuju, tolak, didelegasikan, dieskalasi.
 * Append-only; `decided_by` kosong = keputusan sistem.
 *
 * @property ApprovalDecisionType $decision
 */
class ApprovalDecision extends Model
{
    protected $table = 'approval_decisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecisionType::class,
            'decided_at' => 'datetime',
            'channel' => ApprovalChannel::class,
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ApprovalTask::class, 'approval_task_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }
}
