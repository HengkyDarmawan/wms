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
 * Satu potong fisik berukuran panjang untuk item bermode `piece` (BR-STK-09).
 * Sisa potongan (`is_offcut`) menunjuk induknya dan didahulukan saat pengambilan
 * dengan strategi Sisa potongan dulu (BR-CNV-03).
 */
class Piece extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'pieces';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'length' => 'decimal:4',
            'is_offcut' => 'boolean',
            'is_consumed' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function parentPiece(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_piece_id');
    }

    public function offcuts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_piece_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_consumed', false);
    }

    /** Sisa potongan dulu, lalu yang terpendek yang masih cukup. */
    public function scopeOffcutFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_offcut')->orderBy('length')->orderBy('id');
    }

    /** BR-CNV-03: potongan di bawah panjang minimum dibuang sebagai waste. */
    public function isUsableOffcut(): bool
    {
        $minimum = $this->item?->min_offcut_length;

        return $minimum === null || (float) $this->length >= (float) $minimum;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['item_id', 'piece_no', 'length', 'is_offcut', 'parent_piece_id', 'is_consumed'])
            ->logOnlyDirty();
    }
}
