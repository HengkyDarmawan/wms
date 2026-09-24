<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Models;

use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris hasil CNV: output, offcut, waste, atau kerf (ERD 08c
 * `conversion_outputs`). Item per potong: satu baris = satu potongan baru yang
 * dibuat saat CNV selesai, bersilsilah ke potongan input induknya (BR-CNV-04).
 */
class ConversionOutput extends Model
{
    protected $table = 'conversion_outputs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'output_kind' => ConversionOutputKind::class,
            'stock_status' => StockStatus::class,
            'qty_base' => 'decimal:4',
            'auto_waste' => 'boolean',
        ];
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class)->withoutGlobalScopes();
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

    public function newPiece(): BelongsTo
    {
        return $this->belongsTo(Piece::class, 'new_piece_id');
    }

    public function parentInput(): BelongsTo
    {
        return $this->belongsTo(ConversionInput::class, 'parent_input_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function reversalOfLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_line_id');
    }

    public function trackingLabel(): string
    {
        return (string) ($this->newPiece?->piece_no ?? $this->lot?->lot_no ?? $this->lot_no ?? '');
    }
}
