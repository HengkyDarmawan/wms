<?php

declare(strict_types=1);

namespace App\Domain\Issue\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris ISU: satu bin Gudang Site × item × lot/serial/potongan, jumlah dalam
 * satuan dasar (negatif pada ISU pembalik). Tanda bilangan hanya ada di
 * dokumen; di kartu stok arahnya menjadi pasangan bin asal/tujuan (BR-LED-02).
 */
class MaterialIssueLine extends Model
{
    protected $table = 'material_issue_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id')->withoutGlobalScopes();
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

    public function reversalOfLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_line_id');
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
