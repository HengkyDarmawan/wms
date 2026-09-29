<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Konversi kemasan per item, mis. 1 batang = 6 m (BR-STK-09).
 * `is_nominal_piece` menandai satuan yang dipakai sebagai panjang nominal
 * batang utuh untuk item per potong (BR-STK-09).
 *
 * A-355: `content_qty` + `content_uom_id` menyimpan kalimat yang diketik
 * ("1 DUS berisi 40 PACK"); null = isi dalam satuan dasar (data lama).
 * `qty_base` tetap angka yang dipakai hitungan.
 */
class ItemUomConversion extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'item_uom_conversions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'content_qty' => 'decimal:4',
            'is_nominal_piece' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /** A-355: satuan isi bila kemasan berisi kemasan lain; null = satuan dasar. */
    public function contentUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'content_uom_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function toBase(float $qty): float
    {
        return $qty * (float) $this->qty_base;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['item_id', 'uom_id', 'qty_base', 'content_qty', 'content_uom_id', 'is_nominal_piece', 'is_active'])
            ->logOnlyDirty();
    }
}
