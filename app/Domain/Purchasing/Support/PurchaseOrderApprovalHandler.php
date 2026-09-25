<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Model;

/**
 * Penangan approval PO (Katalog §2.17, D-28, A-212). Satu-satunya jenis
 * dokumen yang membawa **nilai uang** ke mesin approval (`orderValue`).
 * Tanpa aturan yang cocok PO langsung disetujui (A-08). Saat disetujui PO
 * diterbitkan ke WMS sebagai catatan pemesanan (`po_created`, A-213).
 */
class PurchaseOrderApprovalHandler implements ApprovalHandler
{
    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::PurchaseOrder;
    }

    public function approvePermission(): string
    {
        return 'po.approve';
    }

    public function find(int $documentId): ?Model
    {
        return PurchaseOrder::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return PurchaseOrder::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('purchase-orders.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'purchase_order';
    }

    /** @param  PurchaseOrder  $document */
    public function context(Model $document): ApprovalContext
    {
        $baris = PurchaseOrderLine::query()->where('purchase_order_id', $document->getKey())->get();
        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->unique()->all())->get(['id', 'item_category_id', 'ownership_model']);
        $document->loadMissing('vendor:id,vendor_type');

        return new ApprovalContext(
            documentType: ApprovalDocumentType::PurchaseOrder,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->warehouse_id],
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            maxLineQty: (float) ($baris->max(fn (PurchaseOrderLine $l) => (float) $l->qty_base) ?? 0),
            vendorType: $document->vendor?->vendor_type?->value,
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : ($document->created_by !== null ? (int) $document->created_by : null),
            requesterIds: array_values(array_unique(array_filter([(int) $document->created_by, (int) $document->submitted_by]))),
            orderValue: (float) $document->total_amount,
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    /** A-08: tanpa aturan PO disetujui otomatis. */
    public function fallbackSteps(Model $document): array
    {
        return [];
    }

    /** @param  PurchaseOrder  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $po = PurchaseOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->getKey());

        if ($po->status !== PurchaseOrderStatus::PendingApproval) {
            throw PurchasingRuleException::rule('BR-GEN-01', 'Hanya PO yang menunggu approval yang bisa disetujui.');
        }

        $po->forceFill([
            'status' => PurchaseOrderStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        app(PurchaseOrderIssuer::class)->issue($po, $actor);

        activity('purchase_order')->performedOn($po)->causedBy($actor)
            ->log($actor === null ? 'PO disetujui otomatis (tanpa aturan approval) dan diterbitkan ke gudang' : 'PO disetujui dan diterbitkan ke gudang');

        $document->refresh();
    }

    /** @param  PurchaseOrder  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => PurchaseOrderStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
        ])->save();

        activity('purchase_order')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('PO ditolak');
    }
}
