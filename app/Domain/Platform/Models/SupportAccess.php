<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A-27 / BR-SUB-04: izin sementara Admin Company kepada Super Admin untuk membuka
 * data operasional. Berperiode, punya alasan, dan bisa dicabut.
 */
class SupportAccess extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'support_accesses';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
            'link_used_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->whereNull('revoked_at')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->starts_at?->lessThanOrEqualTo(now())
            && $this->ends_at?->greaterThanOrEqualTo(now());
    }
}
