<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Domain\Warehouse\Enums\FloorPlanObjectType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Objek denah gedung (A-320): pintu, dock, jalur forklift, pilar, kantor,
 * area bebas. Tanpa stok (P-01); koordinat relatif sudut kiri-atas gedung;
 * `rotation` kelipatan 90°; nonaktif = tidak digambar (P-03).
 *
 * @property FloorPlanObjectType $object_type
 */
class FloorPlanObject extends Model
{
    use LogsActivity;

    protected $table = 'floor_plan_objects';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'object_type' => FloorPlanObjectType::class,
            'pos_x' => 'decimal:2', 'pos_y' => 'decimal:2',
            'length_m' => 'decimal:2', 'width_m' => 'decimal:2',
            'rotation' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Ukuran tampak atas setelah diputar: 90°/270° menukar panjang & lebar. */
    public function footprint(): array
    {
        $p = (float) $this->length_m;
        $l = (float) $this->width_m;

        return in_array($this->rotation % 180, [90], true) ? [$l, $p] : [$p, $l];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly(['object_type', 'name', 'pos_x', 'pos_y', 'length_m', 'width_m', 'rotation', 'is_active'])
            ->logOnlyDirty();
    }
}
