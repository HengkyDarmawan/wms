<?php

declare(strict_types=1);

namespace App\Domain\Shared\Models;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu transaksi dari perangkat offline (`sync_queue`, A-438, AD-16). */
class SyncItem extends Model
{
    public const RECEIVED = 'received';

    public const POSTED = 'posted';

    public const REVIEW = 'review';

    public const REJECTED = 'rejected';

    /** @var array<string, string> sync_status => label */
    public const LABEL = ['received' => 'Diterima', 'posted' => 'Diposting', 'review' => 'Perlu ditinjau', 'rejected' => 'Ditolak'];

    protected $table = 'sync_queue';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'recorded_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
