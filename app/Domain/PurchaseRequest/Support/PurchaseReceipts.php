<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Support;

use App\Domain\Access\Models\User;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use Illuminate\Support\Collection;

/**
 * Sambungan GRN vendor ↔ catatan pemesanan PRQ (BR-GRN-01, A-47, A-51, A-174).
 *
 * Baris GRN vendor boleh merujuk satu baris catatan pemesanan: gudang GRN =
 * gudang tujuan PRQ, vendor GRN = vendor catatan, item sama, jumlah ≤ sisa
 * yang belum diterima (dikurangi draf GRN lain). Saat GRN `received`, jumlah
 * diterima dicatat di baris catatan dan baris PRQ, lalu PRQ menjadi
 * `partially_fulfilled` atau `fulfilled` (Katalog §2.15).
 */
class PurchaseReceipts
{
    /**
     * Baris catatan pemesanan yang masih menunggu barang untuk gudang & vendor ini.
     *
     * @return Collection<int, PurchaseRequestOrderLine>
     */
    public function openLines(int $warehouseId, int $vendorId): Collection
    {
        return PurchaseRequestOrderLine::query()
            ->with('order:id,purchase_request_id,vendor_id,external_po_no,marketplace_order_no,tracking_no', 'order.purchaseRequest:id,number,status', 'line.item:id,code,name,tracking_mode,has_expiry')
            ->whereColumn('qty_received', '<', 'qty_ordered')
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId)
                ->whereHas('purchaseRequest', fn ($p) => $p->withoutGlobalScopes()
                    ->where('warehouse_id', $warehouseId)
                    ->whereIn('status', [PurchaseRequestStatus::Forwarded->value, PurchaseRequestStatus::PartiallyFulfilled->value])))
            ->orderBy('id')
            ->get();
    }

    /** Dipanggil `SaveGoodsReceipt` untuk setiap baris vendor yang merujuk catatan pemesanan. */
    public function guard(int $orderLineId, int $warehouseId, int $vendorId, int $itemId, float $qty, ?int $receiptId, string $label): void
    {
        $ol = PurchaseRequestOrderLine::query()->with('order.purchaseRequest', 'line.item')->find($orderLineId);
        $prq = $ol?->order?->purchaseRequest;

        if ($ol === null || $prq === null || ! $prq->status->acceptsReceipts()) {
            throw ReceiptRuleException::field('BR-GRN-01', 'purchase_request_order_line_id', $label.': catatan pemesanan PRQ tidak ditemukan atau PRQ belum diteruskan.');
        }

        if ((int) $prq->warehouse_id !== $warehouseId || (int) $ol->order->vendor_id !== $vendorId) {
            throw ReceiptRuleException::field('BR-GRN-01', 'purchase_request_order_line_id', $label.': catatan pemesanan '.$prq->number.' untuk gudang atau vendor lain.');
        }

        if ((int) $ol->line->item_id !== $itemId) {
            throw ReceiptRuleException::field('BR-GRN-01', 'purchase_request_order_line_id', $label.': item berbeda dengan baris '.$prq->number.' ('.$ol->line->item?->code.').');
        }

        $drafLain = (float) GoodsReceiptLine::query()
            ->where('purchase_request_order_line_id', $orderLineId)
            ->whereHas('receipt', fn ($q) => $q->withoutGlobalScopes()->where('status', GoodsReceiptStatus::Draft->value)
                ->when($receiptId !== null, fn ($r) => $r->whereKeyNot($receiptId)))
            ->sum('qty_received');

        $sisa = round($ol->outstandingQty() - $drafLain, 4);

        if ($qty - $sisa > 0.00005) {
            throw ReceiptRuleException::field('BR-GRN-05', 'qty_received', $label.': diterima '.$qty.' melebihi sisa pesanan '.$prq->number.' ('.max(0, $sisa).').');
        }
    }

    /** GRN `received`: jumlah diterima dicatat, status PRQ diturunkan (Katalog §2.15). */
    public function received(GoodsReceipt $receipt, ?User $actor = null): void
    {
        $prqIds = [];

        foreach ($receipt->lines()->whereNotNull('purchase_request_order_line_id')->get() as $gl) {
            $ol = PurchaseRequestOrderLine::query()->with('order')->lockForUpdate()->find($gl->purchase_request_order_line_id);

            if ($ol === null) {
                continue;
            }

            $qty = (float) $gl->qty_received;

            if ($qty - $ol->outstandingQty() > 0.00005) {
                throw ReceiptRuleException::rule('BR-GRN-05', 'Baris '.$gl->item?->code.' melebihi sisa pesanan PRQ ('.$ol->outstandingQty().').');
            }

            $ol->forceFill(['qty_received' => round((float) $ol->qty_received + $qty, 4)])->save();
            PurchaseRequestLine::query()->whereKey($ol->purchase_request_line_id)->increment('qty_received', $qty);
            $prqIds[(int) $ol->order->purchase_request_id] = true;
        }

        foreach (array_keys($prqIds) as $id) {
            $prq = PurchaseRequest::withoutGlobalScopes()->lockForUpdate()->find($id);

            if ($prq === null) {
                continue;
            }

            $penuh = $prq->lines()->get()->every(fn (PurchaseRequestLine $l) => (float) $l->qty_received + 0.00005 >= (float) $l->qty_base);

            $prq->forceFill([
                'status' => $penuh ? PurchaseRequestStatus::Fulfilled : PurchaseRequestStatus::PartiallyFulfilled,
                'fulfilled_at' => $penuh ? now() : null,
            ])->save();

            activity('purchase_request')->performedOn($prq)->causedBy($actor)
                ->withProperties(['grn' => $receipt->number])
                ->log($penuh ? 'Semua barang PRQ diterima ('.$receipt->number.')' : 'Sebagian barang PRQ diterima ('.$receipt->number.')');
        }
    }

    /** @return array<string, mixed> payload `goods_received` tambahan */
    public function eventPayload(GoodsReceiptLine $line): array
    {
        if ($line->purchase_request_order_line_id === null) {
            return [];
        }

        $ol = PurchaseRequestOrderLine::query()->with('order.purchaseRequest:id,number')->find($line->purchase_request_order_line_id);

        return array_filter([
            'purchase_request_order_line_id' => (int) $line->purchase_request_order_line_id,
            'purchase_request_number' => $ol?->order?->purchaseRequest?->number,
            'external_po_no' => $ol?->order?->external_po_no,
        ], fn ($v) => $v !== null);
    }
}
