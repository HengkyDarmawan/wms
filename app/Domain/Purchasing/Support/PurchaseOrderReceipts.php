<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\PurchaseRequest\Events\OrderLinesReceived;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;

/**
 * Pendengar {@see OrderLinesReceived} (A-214): jumlah diterima GRN pada baris
 * catatan pemesanan yang lahir dari PO dicatat di baris PO, lalu status PO
 * diturunkan — `completed` bila setiap baris sudah diterima atau dilepas,
 * selain itu `partially_fulfilled` (Katalog §2.17). Berjalan di transaksi GRN.
 */
class PurchaseOrderReceipts
{
    public function handle(OrderLinesReceived $event): void
    {
        $poIds = [];

        $pasangan = PurchaseRequestOrderLine::query()
            ->whereIn('id', array_keys($event->qtyPerOrderLine))
            ->whereNotNull('purchase_order_line_id')
            ->pluck('purchase_order_line_id', 'id');

        foreach ($pasangan as $orderLineId => $poLineId) {
            $baris = PurchaseOrderLine::query()->lockForUpdate()->find($poLineId);

            if ($baris === null) {
                continue;
            }

            $baris->forceFill(['qty_received' => round((float) $baris->qty_received + $event->qtyPerOrderLine[$orderLineId], 4)])->save();
            $poIds[(int) $baris->purchase_order_id] = true;
        }

        foreach (array_keys($poIds) as $id) {
            $po = PurchaseOrder::withoutGlobalScopes()->lockForUpdate()->find($id);

            if ($po === null || ! $po->status->isOpen()) {
                continue;
            }

            $selesai = $po->lines()->get()->every(fn (PurchaseOrderLine $l) => $l->outstandingQty() <= 0.00005);

            $po->forceFill([
                'status' => $selesai ? PurchaseOrderStatus::Completed : PurchaseOrderStatus::PartiallyFulfilled,
                'completed_at' => $selesai ? now() : null,
            ])->save();

            activity('purchase_order')->performedOn($po)->causedBy($event->actor)
                ->withProperties(['grn' => $event->receiptNumber])
                ->log($selesai ? 'Semua barang PO diterima ('.$event->receiptNumber.')' : 'Sebagian barang PO diterima ('.$event->receiptNumber.')');
        }
    }
}
