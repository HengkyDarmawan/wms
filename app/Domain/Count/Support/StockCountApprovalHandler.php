<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Count\Enums\CountType;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use Illuminate\Database\Eloquent\Model;

/**
 * Penangan approval sesi opname (Katalog §2.13, BR-OPN-06, BR-OPN-09, A-96).
 *
 * - Selalu minimal satu lapis (A-09: penyesuaian hasil opname disetujui di
 *   tingkat sesi): tanpa aturan, sesi bulanan/ad-hoc ke Kepala Gudang,
 *   sesi tahunan dan sesi audit ke Auditor Internal; cadangan Manajemen.
 * - Pemisahan tugas: penghitung sesi dan perekonsiliasi tidak boleh memutus
 *   (BR-OPN-09, BR-APR-03). Sesi tahunan/audit juga tidak boleh diputus
 *   Kepala Gudang gudang cakupan (kecuali ia juga Auditor Internal atau
 *   Manajemen). Semuanya dimasukkan ke daftar "pengaju" konteks, sehingga
 *   mesin melewatinya dan mengalihkan lapis ke atasan/cadangan.
 */
class StockCountApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly StockCountCloser $closer) {}

    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::StockCount;
    }

    public function approvePermission(): string
    {
        return 'count.approve';
    }

    public function find(int $documentId): ?Model
    {
        return StockCount::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return StockCount::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('counts.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'count';
    }

    /** @param  StockCount  $document */
    public function context(Model $document): ApprovalContext
    {
        $selisih = CountLine::query()->with('item:id,item_category_id,ownership_model')
            ->where('stock_count_id', $document->getKey())
            ->whereNotNull('variance_class')->get();

        $terlarang = array_merge(
            $document->counterIds(),
            [(int) $document->submitted_by],
            $this->sesiAudit($document) ? $this->kepalaGudangCakupan($document) : [],
        );

        return new ApprovalContext(
            documentType: ApprovalDocumentType::StockCount,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: $document->warehouseIds(),
            categoryIds: ApprovalContext::withAncestors($selisih->map(fn (CountLine $l) => $l->item?->item_category_id)->filter()->all()),
            ownershipModels: $selisih->map(fn (CountLine $l) => $l->item?->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $selisih->count(),
            maxLineQty: (float) ($selisih->map(fn (CountLine $l) => abs((float) $l->variance_qty))->max() ?? 0),
            countType: $document->count_type->value,
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : null,
            requesterIds: array_values(array_unique(array_filter($terlarang))),
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    /** @param  StockCount  $document */
    public function fallbackSteps(Model $document): array
    {
        $manajemen = Role::findByCode('management')?->id;

        $lapis = $this->sesiAudit($document)
            ? ['approver_type' => ApproverType::Role->value, 'approver_ref_id' => Role::findByCode('internal_auditor')?->id]
            : ['approver_type' => ApproverType::WarehouseHead->value, 'approver_ref_id' => null];

        return [$lapis + [
            'step_no' => 1,
            'decision_mode' => DecisionMode::Any->value,
            'backup_approver_type' => ApproverType::Role->value,
            'backup_ref_id' => $manajemen,
            'timeout_hours' => 24,
            'channel' => 'web',
        ]];
    }

    /** @param  StockCount  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $this->closer->approveAndClose($document, $actor);
    }

    /**
     * Katalog §2.13 tidak punya status `rejected` untuk OPN: sesi tetap
     * `reconciling`, alasan dicatat, dan perekonsiliasi memperbaiki akar
     * masalah lalu mengajukan ulang (A-97). Bin tetap beku.
     *
     * @param  StockCount  $document
     */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'reject_reason_id' => $reasonCodeId,
            'notes' => $notes ?? $document->notes,
        ])->save();

        activity('count')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('Hasil opname ditolak; perbaiki rekonsiliasi lalu ajukan ulang');
    }

    /** BR-OPN-09: sesi tahunan dan sesi audit (dibuat Auditor). */
    private function sesiAudit(StockCount $count): bool
    {
        return $count->count_type === CountType::Annual || $count->is_audit;
    }

    /**
     * Kepala Gudang yang ditugaskan ke gudang cakupan, kecuali yang juga
     * memegang role Auditor Internal atau Manajemen.
     *
     * @return array<int, int>
     */
    private function kepalaGudangCakupan(StockCount $count): array
    {
        $role = Role::findByCode('warehouse_head');

        if ($role === null) {
            return [];
        }

        $gudang = $count->warehouseIds();

        return RoleAssignment::query()->valid()->where('role_id', $role->id)->get()
            ->filter(fn (RoleAssignment $a) => $a->scope_type === ScopeType::All
                || ($a->scope_type === ScopeType::Warehouse && in_array((int) $a->scope_id, $gudang, true)))
            ->pluck('user_id')->map(fn ($v) => (int) $v)->unique()
            ->filter(function (int $id): bool {
                $user = User::query()->find($id);

                return $user !== null && ! $user->hasRoleCode('internal_auditor') && ! $user->hasRoleCode('management');
            })
            ->values()->all();
    }
}
