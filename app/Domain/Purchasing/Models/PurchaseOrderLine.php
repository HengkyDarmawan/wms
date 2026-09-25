<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Domain\Master\Models\Item;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Baris PO: baris PRQ × jumlah satuan dasar × harga satuan (A-210, A-211).
 * `qty_received` dari GRN (A-214); `qty_cancelled` = sisa yang dibatalkan
 * atau ditutup (A-215).
 */
class PurchaseOrderLine extends Model
{
    protected $table = 'purchase_order_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_base' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_amount' => 'decimal:2',
            'qty_received' => 'decimal:4',
            'qty_cancelled' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withoutGlobalScopes();
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class, 'purchase_request_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Pasangan baris di catatan pemesanan PRQ (setelah PO disetujui). */
    public function orderLine(): HasOne
    {
        return $this->hasOne(PurchaseRequestOrderLine::class, 'purchase_order_line_id');
    }

    /** Jumlah yang masih ditunggu dari vendor. */
    public function outstandingQty(): float
    {
        return round(max(0, (float) $this->qty_base - (float) $this->qty_received - (float) $this->qty_cancelled), 4);
    }
}
