<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Gudang (Blueprint §6.2). Hierarki berbentuk pohon; Gudang Site terikat satu
 * proyek dan satu proyek boleh punya beberapa (A-40, BR-WH-04).
 *
 * Model pertama yang memakai {@see ScopedToUser}: daftar gudang dibatasi
 * cakupan penugasan role user yang masuk (BR-ACC-05, AD-06).
 */
class Warehouse extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'warehouses';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** BR-ACC-05: cakupan gudang dicocokkan dengan kunci utama tabel ini. */
    public static function scopeWarehouseColumn(): ?string
    {
        return 'id';
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class, 'warehouse_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** BR-WH-04: hanya Gudang Site yang terikat proyek. */
    public function isSite(): bool
    {
        return $this->type?->isSite() ?? false;
    }

    /** Jalur dari akar, mis. "Gudang Utama Cakung > Gudang Cabang Bekasi". */
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

    /** BR-WH-02: bin virtual Dalam Perjalanan milik gudang ini (BR-STK-13). */
    public function inTransitBin(): ?Bin
    {
        return $this->bins()->where('bin_type', 'in_transit')->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly(['code', 'name', 'warehouse_type_id', 'parent_id', 'project_id', 'head_user_id', 'is_active'])
            ->logOnlyDirty();
    }
}
