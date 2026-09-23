<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\UomCategoryCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Kategori satuan (hitung, panjang, berat, volume, luas). Konversi hanya sah di
 * dalam satu kategori; setiap kategori punya satu satuan acuan (BR-MST-03).
 */
class UomCategory extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'uom_categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function uoms(): HasMany
    {
        return $this->hasMany(Uom::class);
    }

    public function referenceUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'reference_uom_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Kode disimpan huruf besar (BR-MST-01) sedangkan Katalog Status memakai
     * huruf kecil, jadi pencocokannya mengabaikan besar-kecil huruf.
     */
    public function enum(): ?UomCategoryCode
    {
        return UomCategoryCode::tryFrom(mb_strtolower((string) $this->code));
    }

    /** BR-STK-09: mode per potong menuntut satuan dasar berkategori panjang. */
    public function isLength(): bool
    {
        return $this->enum() === UomCategoryCode::Length;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['code', 'name', 'reference_uom_id', 'is_active'])
            ->logOnlyDirty();
    }
}
