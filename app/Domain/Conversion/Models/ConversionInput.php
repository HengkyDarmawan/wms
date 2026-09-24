<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Input CNV: satu bin penyimpanan × item × lot/potongan, jumlah satuan dasar.
 * Potongan selalu dipakai utuh; sisanya menjadi offcut/waste/kerf (A-154).
 */
class ConversionInput extends Model
{
    protected $table = 'conversion_inputs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
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

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function reversalOfLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_line_id');
    }

    /** Hasil yang bersilsilah ke input ini (BR-CNV-04). */
    public function children(): HasMany
    {
        return $this->hasMany(ConversionOutput::class, 'parent_input_id');
    }

    public function trackingLabel(): string
    {
        return (string) ($this->lot?->lot_no ?? $this->piece?->piece_no ?? '');
    }
}
