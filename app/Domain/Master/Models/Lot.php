<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Batch/lot untuk item bermode pelacakan `lot` (BR-STK-05). Dibuat saat
 * penerimaan; nomornya unik per item.
 */
class Lot extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'lots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'date',
            'attributes' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(Serial::class);
    }

    /** Urutan FEFO: kedaluwarsa terdekat dulu, yang tanpa tanggal paling belakang. */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('id');
    }

    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderByRaw('received_at IS NULL, received_at ASC')->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function daysToExpiry(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['item_id', 'lot_no', 'expiry_date', 'received_at', 'vendor_id'])
            ->logOnlyDirty();
    }
}
