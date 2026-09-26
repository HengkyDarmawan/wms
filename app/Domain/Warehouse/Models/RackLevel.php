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

/** Level rak, mis. L2 — induk langsung sebuah bin (Blueprint §6.3). */
class RackLevel extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'rack_levels';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'height_m' => 'decimal:2'];
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly(['rack_id', 'code', 'is_active'])
            ->logOnlyDirty();
    }
}
