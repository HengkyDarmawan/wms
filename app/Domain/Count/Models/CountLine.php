<?php

declare(strict_types=1);

namespace App\Domain\Count\Models;

use App\Domain\Count\Enums\RootCauseCategory;
use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris hitung: snapshot angka sistem saat sesi mulai (BR-OPN-01) dan hasil
 * hitung buta putaran 1 dan 2. Satu baris per bin × item × lot/serial/potongan
 * × kondisi stok — sama dengan kunci saldo.
 *
 * @property StockStatus $stock_status
 * @property VarianceClass|null $variance_class
 * @property RootCauseCategory|null $root_cause
 */
class CountLine extends Model
{
    protected $table = 'count_lines';

    protected $guarded = [];

    protected $attributes = [
        'stock_status' => 'available',
        'is_unexpected' => false,
        'is_recount' => false,
        'system_qty' => 0,
    ];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'variance_class' => VarianceClass::class,
            'root_cause' => RootCauseCategory::class,
            'is_unexpected' => 'boolean',
            'is_recount' => 'boolean',
            'system_qty' => 'decimal:4',
            'counted_qty_r1' => 'decimal:4',
            'counted_qty_r2' => 'decimal:4',
            'final_qty' => 'decimal:4',
            'variance_qty' => 'decimal:4',
            'variance_pct' => 'decimal:4',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class)->withoutGlobalScopes();
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class)->withoutGlobalScopes();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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

    /** Label turunan pelacakan untuk layar: nomor lot, serial, atau potongan. */
    public function trackingLabel(): string
    {
        return (string) ($this->lot?->lot_no ?? $this->serial?->serial_no ?? $this->piece?->piece_no ?? '');
    }

    /** Serial dan potongan dihitung "ada / tidak ada", bukan jumlah bebas. */
    public function isUnitLine(): bool
    {
        return $this->serial_id !== null || $this->piece_id !== null;
    }

    public function hasVariance(): bool
    {
        return $this->variance_qty !== null && abs((float) $this->variance_qty) > 0.00005;
    }
}
