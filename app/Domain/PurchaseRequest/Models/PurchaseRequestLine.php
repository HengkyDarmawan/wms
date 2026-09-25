<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Models;

use App\Domain\Master\Models\Item;
use App\Domain\Request\Models\MaterialRequestLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris PRQ: item × jumlah satuan dasar, tanggal dibutuhkan, dan (untuk
 * backorder) baris REQ penunggu (ERD 08b). `qty_ordered` = Σ baris catatan
 * pemesanan, `qty_received` = Σ GRN (A-51).
 */
class PurchaseRequestLine extends Model
{
    protected $table = 'purchase_request_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required_date' => 'date',
            'qty_base' => 'decimal:4',
            'qty_ordered' => 'decimal:4',
            'qty_received' => 'decimal:4',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class)->withoutGlobalScopes();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestLine::class, 'material_request_line_id');
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(PurchaseRequestOrderLine::class);
    }

    public function unorderedQty(): float
    {
        return round(max(0, (float) $this->qty_base - (float) $this->qty_ordered), 4);
    }
}
