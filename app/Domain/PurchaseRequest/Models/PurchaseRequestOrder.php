<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catatan pemesanan per vendor/toko (A-51): sub-entitas PRQ tanpa status
 * sendiri — nomor PO eksternal, nomor pesanan marketplace, resi, perkiraan
 * datang. Fase 3: PO Purchasing mengisi entitas yang sama.
 */
class PurchaseRequestOrder extends Model
{
    protected $table = 'purchase_request_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'eta_date' => 'date',
            'ordered_at' => 'datetime',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class)->withoutGlobalScopes();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function orderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestOrderLine::class);
    }

    /** Label singkat untuk pilihan di form GRN. */
    public function reference(): string
    {
        return implode(' · ', array_filter([
            $this->external_po_no,
            $this->marketplace_order_no,
            $this->tracking_no !== null ? 'resi '.$this->tracking_no : null,
        ])) ?: 'catatan #'.$this->id;
    }
}
