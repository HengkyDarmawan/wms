<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris catatan pemesanan: baris PRQ × jumlah dipesan ke vendor ini. Baris
 * GRN vendor merujuk baris ini (BR-GRN-01, A-51).
 */
class PurchaseRequestOrderLine extends Model
{
    protected $table = 'purchase_request_order_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'qty_received' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestOrder::class, 'purchase_request_order_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class, 'purchase_request_line_id');
    }

    public function outstandingQty(): float
    {
        return round(max(0, (float) $this->qty_ordered - (float) $this->qty_received), 4);
    }
}
