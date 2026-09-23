<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\ReasonContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Alasan baku per konteks (BR-GEN-02). Alasan wajib dipilih; keterangan bebas
 * tetap opsional (BR-GEN-11).
 *
 * @property ReasonContext $context
 */
class ReasonCode extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'reason_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => ReasonContext::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForContext(Builder $query, ReasonContext $context): Builder
    {
        return $query->where('context', $context->value);
    }

    /**
     * Pilihan untuk dropdown: kode => label.
     *
     * @return array<string, string>
     */
    public static function options(ReasonContext $context): array
    {
        return static::query()->active()->forContext($context)
            ->orderBy('label')
            ->pluck('label', 'code')
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['context', 'code', 'label', 'is_active'])
            ->logOnlyDirty();
    }
}
