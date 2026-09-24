<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris RTV — sekian dari satu baris GRN di bin Karantina, dengan kondisinya.
 *
 * @property StockStatus $stock_status
 */
class VendorReturnLine extends Model
{
    use HasFactory;

    protected $table = 'vendor_return_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'qty_base' => 'decimal:4',
        ];
    }

    public function vendorReturn(): BelongsTo
    {
        return $this->belongsTo(VendorReturn::class)->withoutGlobalScopes();
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
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

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class)->withoutGlobalScopes();
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }
}
