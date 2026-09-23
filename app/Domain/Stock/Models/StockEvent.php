<?php

declare(strict_types=1);

namespace App\Domain\Stock\Models;

use App\Domain\Master\Models\Project;
use App\Domain\Stock\Enums\StockEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbox kejadian stok (AD-05, Aturan Bisnis §14).
 *
 * Ditulis dalam transaksi yang sama dengan kartu stok, sehingga tidak ada
 * kejadian yang hilang bila proses gagal di tengah. Fase 1 berhenti di tabel;
 * adapter HTTP ke Akuntansi dan Purchasing menyusul di Fase 3.
 *
 * @property StockEventType $event_type
 */
class StockEvent extends Model
{
    use HasFactory;

    protected $table = 'stock_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event_type' => StockEventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeUnpublished(Builder $query): Builder
    {
        return $query->whereNull('published_at');
    }

    public function scopeFailing(Builder $query, int $minAttempts = 3): Builder
    {
        return $query->unpublished()->where('attempts', '>=', $minAttempts);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
