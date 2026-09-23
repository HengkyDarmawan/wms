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
 * Satuan ukur. `factor_to_reference` menyatakan berapa satuan acuan yang sama
 * dengan 1 satuan ini (mis. 1 km = 1000 m bila acuannya meter).
 */
class Uom extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'uoms';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'factor_to_reference' => 'decimal:8',
            'rounding' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(UomCategory::class, 'uom_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isReference(): bool
    {
        return $this->category?->reference_uom_id === $this->id;
    }

    /** Konversi kuantitas ke satuan lain dalam kategori yang sama. */
    public function convertTo(self $target, float $qty): float
    {
        if ($this->uom_category_id !== $target->uom_category_id) {
            throw new \InvalidArgumentException('Konversi hanya sah di dalam satu kategori satuan.');
        }

        return $qty * (float) $this->factor_to_reference / (float) $target->factor_to_reference;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['uom_category_id', 'code', 'name', 'factor_to_reference', 'rounding', 'is_active'])
            ->logOnlyDirty();
    }
}
