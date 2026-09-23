<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Enums\DiscrepancyOrigin;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * DSC — selisih pengiriman (BR-SJ-10).
 *
 * Dokumen ini ada supaya barang yang kurang atau rusak punya tempat menunggu
 * keputusan, bukan menguap dari pembukuan. Selama DSC terbuka, barangnya masih
 * tercatat sebagai stok gudang asal di bin Dalam Perjalanan.
 *
 * @property DiscrepancyStatus $status
 * @property DiscrepancyOrigin $origin
 */
class DeliveryDiscrepancy extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'delivery_discrepancies';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'open',
        'origin' => 'partial_delivery',
    ];

    protected function casts(): array
    {
        return [
            'status' => DiscrepancyStatus::class,
            'origin' => DiscrepancyOrigin::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryDiscrepancyLine::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', DiscrepancyStatus::Open->value);
    }

    /** BR-SJ-10: DSC yang terlalu lama menggantung masuk laporan posisi barang. */
    public function scopeStale(Builder $query, int $days): Builder
    {
        return $query->open()->where('created_at', '<=', now()->subDays($days));
    }

    public function ageInDays(): int
    {
        return $this->created_at === null
            ? 0
            : (int) $this->created_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('shipment')
            ->logOnly(['number', 'status', 'origin', 'resolved_by', 'resolved_at'])
            ->logOnlyDirty();
    }
}
