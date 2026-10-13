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
            'is_packaging' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kalimat setara untuk layar (A-411), mis. "1 KM = 1.000 M" atau "1 M = 100 CM"
     * (satuan lebih kecil ditulis dari satuan acuan kategori bila hasilnya bilangan bulat).
     * Null untuk satuan acuan dan kemasan — layar menulis teksnya sendiri.
     */
    public function kalimatSetara(?self $dasar): ?string
    {
        if ($dasar === null || (int) $dasar->id === (int) $this->id || $this->is_packaging) {
            return null;
        }

        $faktor = (float) $this->factor_to_reference;

        if ($faktor > 0 && $faktor < 1) {
            $balik = 1 / $faktor;

            if (abs($balik - round($balik)) < 1e-6) {
                return '1 '.$dasar->code.' = '.self::angka(round($balik)).' '.$this->code;
            }
        }

        return '1 '.$this->code.' = '.self::angka($faktor).' '.$dasar->code;
    }

    /**
     * Angka gaya Indonesia tanpa nol berlebih: 1000 → "1.000", 0.0254 → "0,0254".
     * `$ribuan` = false untuk isian form ("1000", "0,5").
     */
    public static function angka(float $n, bool $ribuan = true): string
    {
        $teks = number_format($n, 8, ',', $ribuan ? '.' : '');

        return rtrim(rtrim($teks, '0'), ',');
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
            ->logOnly(['uom_category_id', 'code', 'name', 'factor_to_reference', 'is_packaging', 'rounding', 'is_active'])
            ->logOnlyDirty();
    }
}
