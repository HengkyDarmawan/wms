<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi per user per kanal (ERD 08c, Blueprint §10). Baris `in_app`
 * tampil di lonceng; baris `email` mencatat email yang terkirim.
 */
class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeInApp(Builder $query): Builder
    {
        return $query->where('channel', 'in_app');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
