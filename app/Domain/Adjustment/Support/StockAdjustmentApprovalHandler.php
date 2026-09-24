<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Support;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustmentLine;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use Illuminate\Database\Eloquent\Model;

/**
 * Penangan approval ADJ manual (Katalog §2.12, 20-approval §3.9).
 *
 * ADJ manual **selalu** butuh minimal satu lapis (A-09, BR-APR-02): tanpa
 * aturan yang cocok, lapis minimumnya Kepala Gudang gudang ADJ dengan
 * cadangan Manajemen. Disetujui = langsung diposting ke kartu stok.
 * ADJ dari opname tidak lewat sini; approval-nya di tingkat sesi (BR-OPN-06).
 */
class StockAdjustmentApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly AdjustmentPoster $poster) {}

    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::StockAdjustment;
    }

    public function approvePermission(): string
    {
        return 'adjustment.approve';
    }

    public function find(int $documentId): ?Model
    {
        return StockAdjustment::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return StockAdjustment::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('adjustments.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'adjustment';
    }

    /** @param  StockAdjustment  $document */
    public function context(Model $document): ApprovalContext
    {
        $baris = StockAdjustmentLine::query()->where('stock_adjustment_id', $document->getKey())->get();
        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->unique())->get(['id', 'item_category_id', 'ownership_model']);

        return new ApprovalContext(
            documentType: ApprovalDocumentType::StockAdjustment,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->warehouse_id],
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            // BR-APR-07: "jumlah di atas batas" = satuan dasar per baris, tanpa tanda.
            maxLineQty: (float) ($baris->map(fn (StockAdjustmentLine $l) => abs((float) $l->qty_delta))->max() ?? 0),
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : null,
            requesterIds: array_filter([(int) $document->submitted_by]),
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    /** A-09: ADJ manual tanpa aturan tetap butuh satu lapis. */
    public function fallbackSteps(Model $document): array
    {
        return [[
            'step_no' => 1,
            'approver_type' => ApproverType::WarehouseHead->value,
            'approver_ref_id' => null,
            'decision_mode' => DecisionMode::Any->value,
            'backup_approver_type' => ApproverType::Role->value,
            'backup_ref_id' => Role::findByCode('management')?->id,
            'timeout_hours' => 24,
            'channel' => 'web',
        ]];
    }

    /** @param  StockAdjustment  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $document->refresh();

        if ($document->status !== StockAdjustmentStatus::PendingApproval) {
            throw AdjustmentRuleException::rule('BR-GEN-01', 'Hanya ADJ berstatus Menunggu Approval yang bisa disetujui.');
        }

        $document->forceFill([
            'status' => StockAdjustmentStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        activity('adjustment')->performedOn($document)->causedBy($actor)->log('ADJ disetujui');

        // Katalog §2.12: approved → posted otomatis.
        $this->poster->post($document, $actor);
    }

    /** @param  StockAdjustment  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => StockAdjustmentStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
            'notes' => $notes ?? $document->notes,
        ])->save();

        activity('adjustment')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('ADJ ditolak');
    }
}
