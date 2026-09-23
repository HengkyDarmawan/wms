<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\CapacityMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Kategori penyimpanan — dipakai lokasi gudang untuk membatasi item apa yang
 * boleh ditaruh dan bagaimana kapasitas ditegakkan (A-37).
 *
 * @property CapacityMode $capacity_mode
 */
class StorageCategory extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'storage_categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'capacity_mode' => CapacityMode::class,
            'is_active' => 'boolean',
        ];
    }

    public function itemCategories(): HasMany
    {
        return $this->hasMany(ItemCategory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['code', 'name', 'capacity_mode', 'is_active'])
            ->logOnlyDirty();
    }
}
