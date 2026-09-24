<?php

declare(strict_types=1);

namespace App\Domain\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token sekali pakai di pesan WhatsApp — stub Fase 2a (BR-APR-10, BR-GEN-10).
 * Belum ada kode yang menulisnya.
 */
class ApprovalToken extends Model
{
    protected $table = 'approval_tokens';

    protected $guarded = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ApprovalTask::class, 'approval_task_id');
    }
}
