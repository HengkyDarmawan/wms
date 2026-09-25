<?php

declare(strict_types=1);

namespace App\Domain\Waste\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;
use App\Domain\Waste\Models\WasteDisposalLine;
use Illuminate\Database\Eloquent\Model;

/**
 * Penangan approval WST (Katalog §2.14 "`submitted → pending_approval /
 * approved` sesuai aturan", 20-approval §3). Tanpa aturan yang cocok, WST
 * langsung disetujui (A-08); ditolak = `rejected` (terminal).
 */
class WasteDisposalApprovalHandler implements ApprovalHandler
{
    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::WasteDisposal;
    }

    public function approvePermission(): string
    {
        return 'waste.approve';
    }

    public function find(int $documentId): ?Model
    {
        return WasteDisposal::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return WasteDisposal::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('waste-disposals.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'waste';
    }

    /** @param  WasteDisposal  $document */
    public function context(Model $document): ApprovalContext
    {
        $baris = WasteDisposalLine::query()->where('waste_disposal_id', $document->getKey())->get();
        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->unique())->get(['id', 'item_category_id', 'ownership_model']);

        return new ApprovalContext(
            documentType: ApprovalDocumentType::WasteDisposal,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->warehouse_id],
            projectId: (int) $document->project_id,
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            maxLineQty: (float) ($baris->map(fn (WasteDisposalLine $l) => abs((float) $l->qty_base))->max() ?? 0),
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : null,
            requesterIds: array_values(array_filter([(int) $document->submitted_by])),
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    /** A-08: tanpa aturan WST disetujui otomatis. */
    public function fallbackSteps(Model $document): array
    {
        return [];
    }

    /** @param  WasteDisposal  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $wst = WasteDisposal::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->getKey());

        if ($wst->status !== WasteDisposalStatus::PendingApproval) {
            throw WasteRuleException::rule('BR-GEN-01', 'Hanya BA waste yang menunggu approval yang bisa disetujui.');
        }

        $wst->forceFill([
            'status' => WasteDisposalStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        activity('waste')->performedOn($wst)->causedBy($actor)
            ->log($actor === null ? 'BA waste disetujui otomatis (tanpa aturan approval)' : 'BA waste disetujui; siap ditutup dengan bukti');

        $document->refresh();
    }

    /** @param  WasteDisposal  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => WasteDisposalStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
        ])->save();

        activity('waste')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('BA waste ditolak');
    }
}
