<?php

declare(strict_types=1);

namespace App\Domain\Stock\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo stok — agregat yang dipelihara transaksional dan bisa dibangun ulang
 * dari kartu stok (BR-STK-01).
 *
 * Satu baris per item × bin × lot/serial/potongan × kondisi. Barisnya dikunci
 * `FOR UPDATE` saat mutasi, dengan urutan kunci tetap untuk mencegah deadlock
 * (AD-04, NFR-13).
 *
 * @property StockStatus $stock_status
 */
class StockBalance extends Model
{
    use HasFactory;

    protected $table = 'stock_balances';

    protected $guarded = [];

    protected $attributes = [
        'stock_status' => 'available',
        'qty_base' => 0,
        'piece_count' => 0,
        'version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'qty_base' => 'decimal:4',
            'piece_count' => 'integer',
            'version' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('stock_status', StockStatus::Available->value);
    }

    public function scopeNonZero(Builder $query): Builder
    {
        return $query->where('qty_base', '>', 0);
    }

    /** BR-STK-13: stok dalam perjalanan tetap dihitung milik gudang asal. */
    public function scopeInWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->whereHas('bin', fn (Builder $q) => $q->where('warehouse_id', $warehouseId));
    }

    public function isEmpty(): bool
    {
        return (float) $this->qty_base <= 0;
    }
}
