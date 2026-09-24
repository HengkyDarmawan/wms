<?php

declare(strict_types=1);

namespace App\Domain\Count\Models;

use App\Domain\Access\Models\User;
use App\Domain\Count\Enums\CountAssignmentStatus;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan penghitung — siapa menghitung bin mana pada putaran berapa
 * (Glosarium §8 `count_assignment`). Putaran 2 = hitung ulang oleh orang
 * yang berbeda dari putaran 1 (BR-OPN-05).
 *
 * @property CountAssignmentStatus $status
 */
class CountAssignment extends Model
{
    protected $table = 'count_assignments';

    protected $guarded = [];

    protected $attributes = [
        'round' => 1,
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'status' => CountAssignmentStatus::class,
            'counted_at' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class)->withoutGlobalScopes();
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class)->withoutGlobalScopes();
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counter_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CountAssignmentStatus::Pending->value);
    }

    public function isDone(): bool
    {
        return $this->status === CountAssignmentStatus::Done;
    }

    /**
     * Baris yang harus dihitung penugasan ini: putaran 1 semua baris bin itu,
     * putaran 2 hanya baris yang masuk hitung ulang.
     *
     * @return \Illuminate\Database\Eloquent\Builder<CountLine>
     */
    public function linesQuery(): Builder
    {
        return CountLine::query()
            ->where('stock_count_id', $this->stock_count_id)
            ->where('bin_id', $this->bin_id)
            ->when($this->round === 2, fn (Builder $q) => $q->where('is_recount', true));
    }
}
