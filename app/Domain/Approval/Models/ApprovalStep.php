<?php

declare(strict_types=1);

namespace App\Domain\Approval\Models;

use App\Domain\Approval\Enums\ApprovalChannel;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lapis aturan approval (Blueprint §8.1).
 *
 * @property int $step_no
 * @property ApproverType $approver_type
 * @property DecisionMode $decision_mode
 * @property ApproverType|null $backup_approver_type
 */
class ApprovalStep extends Model
{
    protected $table = 'approval_steps';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'step_no' => 'integer',
            'approver_type' => ApproverType::class,
            'approver_ref_id' => 'integer',
            'decision_mode' => DecisionMode::class,
            'backup_approver_type' => ApproverType::class,
            'backup_ref_id' => 'integer',
            'timeout_hours' => 'integer',
            'channel' => ApprovalChannel::class,
            'require_pin' => 'boolean',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id');
    }

    /**
     * Bentuk lapis yang disalin ke snapshot dan dipakai perencana.
     *
     * @return array<string, mixed>
     */
    public function toPlanInput(): array
    {
        return [
            'step_no' => $this->step_no,
            'approver_type' => $this->approver_type->value,
            'approver_ref_id' => $this->approver_ref_id,
            'decision_mode' => $this->decision_mode->value,
            'backup_approver_type' => $this->backup_approver_type?->value,
            'backup_ref_id' => $this->backup_ref_id,
            'timeout_hours' => $this->timeout_hours ?: 24,
            'channel' => $this->channel?->value ?? 'web',
        ];
    }
}
