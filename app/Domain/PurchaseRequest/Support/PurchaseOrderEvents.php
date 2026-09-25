<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Support;

use App\Domain\Access\Models\User;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use Illuminate\Support\Facades\DB;

/**
 * Sisi WMS dari kejadian Purchasing → WMS (purchasing/01 §5.2, A-213):
 * `po_created`, `po_updated`, `po_cancelled`. Menerima data polos tanpa harga
 * sehingga kontraknya sama dengan endpoint integrasi `[F3]`; di Fase 1b
 * dipanggil langsung oleh modul Purchasing dalam transaksi yang sama.
 */
class PurchaseOrderEvents
{
    /**
     * `po_created`: satu catatan pemesanan per PRQ; PRQ `approved → forwarded`.
     *
     * @param  array{po_id: int, po_number: string, vendor_id: int, warehouse_id: int, eta_date: ?string, lines: array<int, array{po_line_id: int, purchase_request_line_id: int, qty: float}>}  $po
     */
    public function created(array $po, ?User $actor = null): void
    {
        DB::transaction(function () use ($po, $actor) {
            $perPrq = [];

            foreach ($po['lines'] as $baris) {
                $l = PurchaseRequestLine::query()->with('item:id,code')->lockForUpdate()->find($baris['purchase_request_line_id'])
                    ?? throw PurchaseRequestRuleException::rule('BR-GEN-11', 'Baris PRQ untuk '.$po['po_number'].' tidak ditemukan.');

                $prq = PurchaseRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($l->purchase_request_id);

                if (! $prq->status->acceptsOrders() || (int) $prq->warehouse_id !== (int) $po['warehouse_id']) {
                    throw PurchaseRequestRuleException::rule('BR-GEN-01', $prq->number.' ('.$prq->status->label().') tidak lagi menerima pesanan untuk gudang ini.');
                }

                if ($baris['qty'] - $l->unorderedQty() > 0.00005) {
                    throw PurchaseRequestRuleException::rule('BR-REQ-08', $l->item?->code.' di '.$prq->number.': dipesan '.$baris['qty'].' melebihi sisa yang belum dipesan ('.$l->unorderedQty().').');
                }

                $perPrq[(int) $prq->id][] = $baris;
            }

            foreach ($perPrq as $prqId => $daftar) {
                $prq = PurchaseRequest::withoutGlobalScopes()->findOrFail($prqId);

                $catatan = PurchaseRequestOrder::create([
                    'purchase_request_id' => $prqId,
                    'purchase_order_id' => $po['po_id'],
                    'vendor_id' => $po['vendor_id'],
                    'external_po_no' => $po['po_number'],
                    'eta_date' => $po['eta_date'],
                    'ordered_by' => $actor?->id,
                    'ordered_at' => now(),
                    'notes' => 'Dari PO '.$po['po_number'],
                ]);

                foreach ($daftar as $baris) {
                    PurchaseRequestOrderLine::create([
                        'purchase_request_order_id' => $catatan->id,
                        'purchase_request_line_id' => $baris['purchase_request_line_id'],
                        'purchase_order_line_id' => $baris['po_line_id'],
                        'qty_ordered' => $baris['qty'],
                        'po_line_ref' => (string) $baris['po_line_id'],
                    ]);

                    PurchaseRequestLine::query()->whereKey($baris['purchase_request_line_id'])->increment('qty_ordered', $baris['qty']);
                }

                if ($prq->status === PurchaseRequestStatus::Approved) {
                    $prq->forceFill(['status' => PurchaseRequestStatus::Forwarded, 'forwarded_by' => $actor?->id, 'forwarded_at' => now()])->save();
                }

                activity('purchase_request')->performedOn($prq)->causedBy($actor)
                    ->withProperties(['po' => $po['po_number'], 'baris' => count($daftar)])
                    ->log('Dipesan lewat '.$po['po_number'].' (po_created)');
            }
        });
    }

    /** `po_updated`: perkiraan datang catatan pemesanan ikut PO. */
    public function updated(int $poId, ?string $etaDate): void
    {
        PurchaseRequestOrder::query()->where('purchase_order_id', $poId)->update(['eta_date' => $etaDate]);
    }

    /**
     * `po_cancelled` (PO dibatalkan atau ditutup sisa): jumlah dipesan dikurangi
     * sisa yang tidak jadi datang, sehingga baris PRQ bisa dipesan lagi. PRQ
     * tetap *Diteruskan* (purchasing/01 §5.2).
     *
     * @param  array<int, float>  $qtyPerPoLine  po_line_id => jumlah dilepas
     */
    public function cancelled(string $poNumber, array $qtyPerPoLine, ?User $actor = null): void
    {
        DB::transaction(function () use ($poNumber, $qtyPerPoLine, $actor) {
            $prqIds = [];

            foreach ($qtyPerPoLine as $poLineId => $qty) {
                $ol = PurchaseRequestOrderLine::query()->with('order')->lockForUpdate()->where('purchase_order_line_id', $poLineId)->first();

                if ($ol === null || $qty <= 0) {
                    continue;
                }

                $lepas = round(min($qty, $ol->outstandingQty()), 4);
                $ol->forceFill(['qty_ordered' => round((float) $ol->qty_ordered - $lepas, 4)])->save();
                PurchaseRequestLine::query()->whereKey($ol->purchase_request_line_id)->decrement('qty_ordered', $lepas);
                $prqIds[(int) $ol->order->purchase_request_id] = true;
            }

            foreach (array_keys($prqIds) as $id) {
                activity('purchase_request')->performedOn(PurchaseRequest::withoutGlobalScopes()->findOrFail($id))->causedBy($actor)
                    ->withProperties(['po' => $poNumber])
                    ->log('Sisa pesanan '.$poNumber.' dilepas (po_cancelled); bisa dipesan lagi');
            }
        });
    }

    /** PRQ yang masih menunggu barang dari PO (A-215: batalkan/tutup PO dulu). */
    public static function hasOpenPurchaseOrder(PurchaseRequest $prq): bool
    {
        return PurchaseRequestOrderLine::query()
            ->whereNotNull('purchase_order_line_id')
            ->whereColumn('qty_received', '<', 'qty_ordered')
            ->whereHas('order', fn ($q) => $q->where('purchase_request_id', $prq->id))
            ->exists();
    }
}
