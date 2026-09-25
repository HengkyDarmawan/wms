<?php

declare(strict_types=1);

namespace App\Domain\Waste\Models;

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
 * Baris WST: satu saldo bin Waste × item × lot/serial/potongan × kondisi
 * (ERD 08c `waste_disposal_lines`, A-159). Potongan dan serial selalu utuh.
 */
class WasteDisposalLine extends Model
{
    protected $table = 'waste_disposal_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'qty_base' => 'decimal:4',
        ];
    }

    public function wasteDisposal(): BelongsTo
    {
        return $this->belongsTo(WasteDisposal::class)->withoutGlobalScopes();
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

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function trackingLabel(): string
    {
        return (string) ($this->lot?->lot_no ?? $this->serial?->serial_no ?? $this->piece?->piece_no ?? '');
    }
}
