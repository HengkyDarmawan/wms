<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemVendor;
use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * Penangan approval PRQ (Katalog §2.15, BR-APR-07, 20-approval §3). Approval
 * opsional: tanpa aturan yang cocok PRQ langsung disetujui (A-08).
 *
 * Kondisi khusus PRQ: **asal PRQ** dan **jenis vendor** — PRQ belum punya
 * vendor saat diajukan, jadi jenis vendor dibaca dari vendor utama item di
 * `item_vendors` (A-52, A-173).
 */
class PurchaseRequestApprovalHandler implements ApprovalHandler
{
    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::PurchaseRequest;
    }

    public function approvePermission(): string
    {
        return 'pr.approve';
    }

    public function find(int $documentId): ?Model
    {
        return PurchaseRequest::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return PurchaseRequest::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('purchase-requests.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'purchase_request';
    }

    /** @param  PurchaseRequest  $document */
    public function context(Model $document): ApprovalContext
    {
        $baris = PurchaseRequestLine::query()->where('purchase_request_id', $document->getKey())->get();
        $itemIds = $baris->pluck('item_id')->unique()->all();
        $item = Item::query()->whereIn('id', $itemIds)->get(['id', 'item_category_id', 'ownership_model']);

        return new ApprovalContext(
            documentType: ApprovalDocumentType::PurchaseRequest,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->warehouse_id],
            projectId: $document->project_id !== null ? (int) $document->project_id : null,
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            maxLineQty: (float) ($baris->max(fn (PurchaseRequestLine $l) => (float) $l->qty_base) ?? 0),
            vendorType: $this->jenisVendor((int) $document->getKey(), $itemIds),
            purchaseRequestOrigin: $document->origin?->value,
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : ($document->created_by !== null ? (int) $document->created_by : null),
            requesterIds: array_values(array_unique(array_filter([(int) $document->created_by, (int) $document->submitted_by]))),
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    /** A-08: tanpa aturan PRQ disetujui otomatis. */
    public function fallbackSteps(Model $document): array
    {
        return [];
    }

    /** @param  PurchaseRequest  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $prq = PurchaseRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->getKey());

        if ($prq->status !== PurchaseRequestStatus::PendingApproval) {
            throw PurchaseRequestRuleException::rule('BR-GEN-01', 'Hanya PRQ yang menunggu approval yang bisa disetujui.');
        }

        $prq->forceFill([
            'status' => PurchaseRequestStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        activity('purchase_request')->performedOn($prq)->causedBy($actor)
            ->log($actor === null ? 'PRQ disetujui otomatis (tanpa aturan approval); siap ditindaklanjuti' : 'PRQ disetujui; siap ditindaklanjuti');

        app(DomainNotifications::class)->purchaseRequestApproved($prq, $actor);

        $document->refresh();
    }

    /** @param  PurchaseRequest  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => PurchaseRequestStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
        ])->save();

        activity('purchase_request')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('PRQ ditolak');
    }

    /**
     * Jenis vendor untuk kondisi approval (A-173): vendor catatan pemesanan bila
     * sudah ada, selain itu vendor utama item (`item_vendors`). Bila beberapa
     * jenis, dipakai yang paling berisiko: toko online → perorangan → toko →
     * perusahaan.
     *
     * @param  array<int, int>  $itemIds
     */
    private function jenisVendor(int $prqId, array $itemIds): ?string
    {
        $jenis = PurchaseRequestOrder::query()->with('vendor:id,vendor_type')
            ->where('purchase_request_id', $prqId)->get()
            ->map(fn ($o) => $o->vendor?->vendor_type?->value);

        if ($jenis->filter()->isEmpty()) {
            $jenis = ItemVendor::query()->with('vendor:id,vendor_type')
                ->whereIn('item_id', $itemIds)
                ->orderByDesc('is_preferred')->orderBy('priority')
                ->get()
                ->groupBy('item_id')
                ->map(fn ($g) => $g->first()?->vendor?->vendor_type?->value);
        }

        foreach (['online_marketplace', 'individual', 'shop', 'company'] as $urut) {
            if ($jenis->contains($urut)) {
                return $urut;
            }
        }

        return null;
    }
}
