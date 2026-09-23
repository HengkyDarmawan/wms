<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\RemovalStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Kategori item bertingkat. Menyimpan nilai bawaan yang diwarisi item:
 * strategi pengambilan, kategori penyimpanan, dan toleransi opname (BR-OPN-04).
 *
 * @property RemovalStrategy|null $removal_strategy
 */
class ItemCategory extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'item_categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'removal_strategy' => RemovalStrategy::class,
            'tolerance_pct' => 'decimal:2',
            'tolerance_abs' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function storageCategory(): BelongsTo
    {
        return $this->belongsTo(StorageCategory::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Jalur dari akar, mis. "Material > Pipa > PVC". */
    public function path(string $separator = ' > '): string
    {
        $bagian = [$this->name];
        $induk = $this->parent;
        $batas = 0;

        while ($induk !== null && $batas < 10) {
            array_unshift($bagian, $induk->name);
            $induk = $induk->parent;
            $batas++;
        }

        return implode($separator, $bagian);
    }

    /** Toleransi opname efektif: milik sendiri, kalau kosong warisi induk. */
    public function effectiveTolerance(): array
    {
        $kategori = $this;
        $batas = 0;

        while ($kategori !== null && $batas < 10) {
            if ($kategori->tolerance_pct !== null || $kategori->tolerance_abs !== null) {
                return [
                    'pct' => $kategori->tolerance_pct === null ? null : (float) $kategori->tolerance_pct,
                    'abs' => $kategori->tolerance_abs === null ? null : (float) $kategori->tolerance_abs,
                ];
            }

            $kategori = $kategori->parent;
            $batas++;
        }

        return ['pct' => null, 'abs' => null];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['parent_id', 'code', 'name', 'storage_category_id', 'removal_strategy', 'tolerance_pct', 'tolerance_abs', 'abc_class', 'is_active'])
            ->logOnlyDirty();
    }
}
