<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Rak di dalam zona, mis. R03 (Blueprint §6.3). Ukuran & posisi di denah
 * opsional (A-254); rak area = satu bin untuk seluruh rak/zona (A-255).
 */
class Rack extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'racks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_area' => 'boolean',
            'pos_x' => 'decimal:2',
            'pos_y' => 'decimal:2',
            'length_m' => 'decimal:2',
            'width_m' => 'decimal:2',
            'height_m' => 'decimal:2',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(RackLevel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly(['zone_id', 'code', 'name', 'is_active', 'is_area', 'pos_x', 'pos_y', 'length_m', 'width_m', 'height_m', 'orientation'])
            ->logOnlyDirty();
    }
}
