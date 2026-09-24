<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Receipt\Models\VendorReturnLine;
use Illuminate\Database\Eloquent\Model;

/**
 * Penangan approval RTV (Katalog §2.16) — menggantikan jalur sementara
 * A-80 (20-approval §13). Persetujuan tidak menggerakkan stok; stok keluar
 * baru saat RTV dikirim.
 */
class VendorReturnApprovalHandler implements ApprovalHandler
{
    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::VendorReturn;
    }

    public function approvePermission(): string
    {
        return 'vendor_return.approve';
    }

    public function find(int $documentId): ?Model
    {
        return VendorReturn::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return VendorReturn::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('vendor-returns.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'receipt';
    }

    /** @param  VendorReturn  $document */
    public function context(Model $document): ApprovalContext
    {
        $baris = VendorReturnLine::query()->where('vendor_return_id', $document->getKey())->get();
        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->unique())->get(['id', 'item_category_id', 'ownership_model']);

        return new ApprovalContext(
            documentType: ApprovalDocumentType::VendorReturn,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->warehouse_id],
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            maxLineQty: (float) ($baris->max('qty_base') ?? 0),
            vendorType: Vendor::query()->whereKey($document->vendor_id)->first()?->vendor_type?->value,
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : null,
            requesterIds: array_filter([(int) $document->submitted_by]),
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    public function fallbackSteps(Model $document): array
    {
        return [];
    }

    /** @param  VendorReturn  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $document->refresh();

        if ($document->status !== VendorReturnStatus::PendingApproval) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya RTV berstatus Menunggu Approval yang bisa disetujui.');
        }

        $document->forceFill([
            'status' => VendorReturnStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        activity('receipt')->performedOn($document)->causedBy($actor)->log('RTV disetujui');
    }

    /** @param  VendorReturn  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => VendorReturnStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
            'notes' => $notes ?? $document->notes,
        ])->save();

        activity('receipt')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('RTV ditolak');
    }
}
