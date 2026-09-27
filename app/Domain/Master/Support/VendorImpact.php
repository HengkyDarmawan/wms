<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Collection;

/**
 * Dampak menonaktifkan vendor (A-310): dokumen terbuka yang masih menunjuk
 * vendor itu — tanpa nilai uang (D-07). Nonaktif tetap boleh; dokumen terbuka
 * berjalan terus dan bisa ditutup/dibatalkan sendiri, vendor hanya hilang dari
 * pilihan & saran baru.
 */
class VendorImpact
{
    public const PRQ_SELESAI = [PurchaseRequestStatus::Fulfilled, PurchaseRequestStatus::Cancelled, PurchaseRequestStatus::Rejected];

    public const PO_TERBUKA = [PurchaseOrderStatus::Draft, PurchaseOrderStatus::PendingApproval, PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyFulfilled];

    /**
     * @return array{pesanan: Collection<int, PurchaseRequestOrder>, prq: Collection<int, PurchaseRequest>, po: Collection<int, PurchaseOrder>}
     */
    public function for(Vendor $vendor): array
    {
        $pesanan = PurchaseRequestOrder::query()
            ->where('vendor_id', $vendor->id)
            ->whereHas('lines', fn ($q) => $q->whereColumn('qty_received', '<', 'qty_ordered'))
            ->whereHas('purchaseRequest', fn ($q) => $q->withoutGlobalScopes()
                ->whereNotIn('status', array_map(fn ($s) => $s->value, self::PRQ_SELESAI)))
            ->orderBy('id')->get(['id', 'purchase_request_id', 'purchase_order_id', 'external_po_no', 'marketplace_order_no', 'tracking_no', 'ordered_at']);

        return [
            'pesanan' => $pesanan,
            'prq' => PurchaseRequest::query()->withoutGlobalScopes()->whereIn('id', $pesanan->pluck('purchase_request_id')->unique())
                ->orderBy('id')->get(['id', 'number', 'status']),
            'po' => PurchaseOrder::query()->withoutGlobalScopes()->where('vendor_id', $vendor->id)
                ->whereIn('status', array_map(fn ($s) => $s->value, self::PO_TERBUKA))
                ->orderBy('id')->get(['id', 'number', 'status']),
        ];
    }
}
