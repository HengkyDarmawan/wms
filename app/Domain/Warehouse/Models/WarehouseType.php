<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tipe gudang — master yang bisa ditambah company (Blueprint §6.2).
 * Bawaan: `main`, `branch`, `site`. Tipe bawaan tidak bisa dihapus.
 */
class WarehouseType extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * Kode tipe bawaan yang punya arti khusus di aturan bisnis.
     *
     * Disimpan huruf besar mengikuti BR-MST-01, sedangkan Katalog Status
     * menulisnya huruf kecil; pencocokannya mengabaikan besar-kecil huruf.
     */
    public const SITE = 'SITE';

    public const MAIN = 'MAIN';

    public const BRANCH = 'BRANCH';

    protected $table = 'warehouse_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** BR-WH-04: hanya tipe `site` yang terikat proyek. */
    public function isSite(): bool
    {
        return mb_strtoupper((string) $this->code) === self::SITE;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly(['code', 'name', 'is_builtin', 'is_active'])
            ->logOnlyDirty();
    }
}
