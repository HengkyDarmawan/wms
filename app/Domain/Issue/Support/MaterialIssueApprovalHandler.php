<?php

declare(strict_types=1);

namespace App\Domain\Issue\Support;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Models\MaterialIssueLine;
use App\Domain\Master\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penangan approval **ISU pembalik** (Katalog §2.9 catatan, BR-GEN-04,
 * 20-approval §3.9). ISU biasa tidak lewat mesin approval.
 *
 * Pembalik selalu butuh minimal satu lapis (A-150): tanpa aturan yang cocok,
 * lapis minimumnya Kepala Gudang Gudang Site dengan cadangan Manajemen.
 * Selama menunggu, ISU tetap `draft`; disetujui = pergerakan asal dibalik dan
 * ISU `confirmed`; ditolak = tetap `draft` dan boleh diajukan ulang atau
 * dibatalkan (tanpa status baru, A-150).
 */
class MaterialIssueApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly IssuePoster $poster) {}

    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::MaterialIssue;
    }

    public function approvePermission(): string
    {
        return 'issue.approve';
    }

    public function find(int $documentId): ?Model
    {
        return MaterialIssue::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return MaterialIssue::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('issues.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'issue';
    }

    /** @param  MaterialIssue  $document */
    public function context(Model $document): ApprovalContext
    {
        $baris = MaterialIssueLine::query()->where('material_issue_id', $document->getKey())->get();
        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->unique())->get(['id', 'item_category_id', 'ownership_model']);
        $pengaju = array_values(array_unique(array_filter([(int) $document->issued_by, (int) $document->submitted_by])));

        return new ApprovalContext(
            documentType: ApprovalDocumentType::MaterialIssue,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->warehouse_id],
            projectId: (int) $document->project_id,
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            // BR-APR-07: jumlah satuan dasar per baris, tanpa tanda.
            maxLineQty: (float) ($baris->map(fn (MaterialIssueLine $l) => abs((float) $l->qty_base))->max() ?? 0),
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : ($document->issued_by !== null ? (int) $document->issued_by : null),
            requesterIds: $pengaju,
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    /** A-150: ISU pembalik tanpa aturan tetap butuh satu lapis. */
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

    /** @param  MaterialIssue  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $isu = MaterialIssue::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->getKey());

            if ($isu->status !== MaterialIssueStatus::Draft || ! $isu->isReversal()) {
                throw IssueRuleException::rule('BR-GEN-01', 'Hanya ISU pembalik berstatus Draf yang bisa disetujui.');
            }

            $this->poster->reverse($isu);

            $isu->forceFill([
                'status' => MaterialIssueStatus::Confirmed,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
                'reject_reason_id' => null,
                'confirmed_by' => $isu->submitted_by,
                'confirmed_at' => now(),
            ])->save();

            activity('issue')->performedOn($isu)->causedBy($actor)
                ->withProperties(['pembalik_dari' => $isu->reversalOf?->number])
                ->log('ISU pembalik disetujui; pemakaian asal dibalik di kartu stok');
        });

        $document->refresh();
    }

    /** @param  MaterialIssue  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
        ])->save();

        activity('issue')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('ISU pembalik ditolak; tetap Draf (ajukan ulang atau batalkan)');
    }
}
