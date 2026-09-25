<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\Access\Models\User;
use App\Domain\PurchaseRequest\Support\PurchaseOrderEvents;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;

/**
 * Mengirim kejadian PO ke WMS (purchasing/01 §5.2, A-213) — tanpa harga.
 * Satu aplikasi, jadi "kirim" = panggilan langsung ke sisi WMS
 * ({@see PurchaseOrderEvents}) dalam transaksi yang sama.
 */
class PurchaseOrderIssuer
{
    public function __construct(private readonly PurchaseOrderEvents $wms) {}

    /** `po_created` saat PO disetujui. */
    public function issue(PurchaseOrder $po, ?User $actor = null): void
    {
        $this->wms->created([
            'po_id' => (int) $po->id,
            'po_number' => (string) $po->number,
            'vendor_id' => (int) $po->vendor_id,
            'warehouse_id' => (int) $po->warehouse_id,
            'eta_date' => $po->eta_date?->toDateString(),
            'lines' => $po->lines()->orderBy('id')->get()->map(fn (PurchaseOrderLine $l) => [
                'po_line_id' => (int) $l->id,
                'purchase_request_line_id' => (int) $l->purchase_request_line_id,
                'qty' => (float) $l->qty_base,
            ])->all(),
        ], $actor);
    }

    /** `po_updated` saat ETA berubah. */
    public function update(PurchaseOrder $po): void
    {
        $this->wms->updated((int) $po->id, $po->eta_date?->toDateString());
    }

    /**
     * `po_cancelled`: sisa baris yang tidak jadi datang dilepas.
     *
     * @param  array<int, float>  $qtyPerLine  po_line_id => jumlah dilepas
     */
    public function release(PurchaseOrder $po, array $qtyPerLine, ?User $actor = null): void
    {
        $this->wms->cancelled((string) $po->number, $qtyPerLine, $actor);
    }
}
