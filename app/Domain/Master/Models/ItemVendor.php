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
 * Vendor tetap per item dengan urutan prioritas (A-52). Tanpa harga (D-07);
 * pemilihan harga terjadi di modul Purchasing.
 */
class ItemVendor extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'item_vendors';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_preferred' => 'boolean',
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

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_preferred')->orderBy('priority');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['item_id', 'vendor_id', 'priority', 'is_preferred', 'notes'])
            ->logOnlyDirty();
    }
}
