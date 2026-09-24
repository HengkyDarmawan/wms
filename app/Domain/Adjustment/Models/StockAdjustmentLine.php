<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Models;

use App\Domain\Count\Models\CountLine;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris ADJ: satu bin × item × lot/serial/potongan × kondisi, jumlah ±
 * dalam satuan dasar. Tanda bilangan hanya ada di dokumen; saat diposting
 * arahnya menjadi pasangan bin asal/tujuan (BR-LED-02).
 *
 * @property StockStatus $stock_status
 */
class StockAdjustmentLine extends Model
{
    protected $table = 'stock_adjustment_lines';

    protected $guarded = [];

    protected $attributes = [
        'stock_status' => 'available',
    ];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'qty_delta' => 'decimal:4',
            'piece_length' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id')->withoutGlobalScopes();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class)->withoutGlobalScopes();
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

    public function countLine(): BelongsTo
    {
        return $this->belongsTo(CountLine::class);
    }

    public function reversalOfLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_line_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function isIncrease(): bool
    {
        return (float) $this->qty_delta > 0;
    }

    public function trackingLabel(): string
    {
        return (string) ($this->lot?->lot_no ?? $this->lot_no
            ?? $this->serial?->serial_no ?? $this->serial_no
            ?? $this->piece?->piece_no
            ?? ($this->piece_length !== null ? 'potongan baru '.rtrim(rtrim(number_format((float) $this->piece_length, 4, '.', ''), '0'), '.') : ''));
    }
}
