<?php

declare(strict_types=1);

namespace App\Domain\Stock\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\ReservationStatus;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Reservasi — jumlah yang dijanjikan untuk sebuah dokumen (BR-STK-03).
 *
 * Sengaja terpisah dari kartu stok: menjanjikan barang bukan memindahkannya.
 * Stok tersedia = saldo Tersedia dikurangi reservasi yang masih aktif.
 *
 * @property ReservationLevel $level
 * @property ReservationStatus $status
 */
class StockReservation extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'stock_reservations';

    protected $guarded = [];

    protected $attributes = [
        'level' => 'soft',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'level' => ReservationLevel::class,
            'status' => ReservationStatus::class,
            'qty_base' => 'decimal:4',
            'released_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Active->value);
    }

    public function scopeForDocument(Builder $query, string $type, int $id): Builder
    {
        return $query->where('document_type', $type)->where('document_id', $id);
    }

    /**
     * BR-STK-16: reservasi aktif yang belum berubah menjadi alokasi keras
     * setelah sekian hari. Ambangnya dari pengaturan company.
     */
    public function scopeStale(Builder $query, int $days): Builder
    {
        return $query->active()
            ->where('level', ReservationLevel::Soft->value)
            ->where('created_at', '<=', now()->subDays($days));
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
            ->useLogName('stock')
            ->logOnly(['item_id', 'warehouse_id', 'bin_id', 'qty_base', 'level', 'status', 'released_reason'])
            ->logOnlyDirty();
    }
}
