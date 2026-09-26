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

/** Zona gudang, mis. A = material besi (Blueprint §6.3). */
class Zone extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'zones';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'length_m' => 'decimal:2', 'width_m' => 'decimal:2'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly(['warehouse_id', 'code', 'name', 'is_active'])
            ->logOnlyDirty();
    }
}
