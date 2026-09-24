<?php

declare(strict_types=1);

namespace App\Domain\Approval\Models;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDecisionType;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tugas approval satu orang pada satu lapis (ERD 08c).
 *
 * Delegasi (BR-APR-05) dan eskalasi (BR-APR-06) tidak mengubah tugas lama:
 * tugas baru dibuat dan menunjuk asalnya, supaya jejaknya bisa diaudit.
 *
 * @property ApprovalTaskStatus $status
 */
class ApprovalTask extends Model
{
    protected $table = 'approval_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'step_no' => 'integer',
            'approver_user_id' => 'integer',
            'delegated_from_user_id' => 'integer',
            'escalated_from_task_id' => 'integer',
            'due_at' => 'datetime',
            'status' => ApprovalTaskStatus::class,
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ApprovalSnapshot::class, 'approval_snapshot_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_from_user_id');
    }

    public function escalatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'escalated_from_task_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class)->orderBy('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ApprovalTaskStatus::Open->value);
    }

    public function isOverdue(): bool
    {
        return $this->status === ApprovalTaskStatus::Open && $this->due_at !== null && $this->due_at->isPast();
    }

    public function wasApproved(): bool
    {
        return $this->status === ApprovalTaskStatus::Decided
            && $this->decisions->contains(fn (ApprovalDecision $d) => $d->decision === ApprovalDecisionType::Approved);
    }
}
