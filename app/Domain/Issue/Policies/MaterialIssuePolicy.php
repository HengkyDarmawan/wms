<?php

declare(strict_types=1);

namespace App\Domain\Issue\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Models\MaterialIssue;

/**
 * Izin pemakaian material (23-pemakaian §2, A-119). Cakupan gudang/proyek
 * lewat global scope model (di luar cakupan = 404).
 */
class MaterialIssuePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('issue.view');
    }

    public function view(User $actor, MaterialIssue $issue): bool
    {
        return $actor->hasPermission('issue.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('issue.create');
    }

    /** Draf ISU biasa diubah pembuatnya atau staf yang akan mengonfirmasi. */
    public function update(User $actor, MaterialIssue $issue): bool
    {
        return $actor->hasPermission('issue.create')
            && $issue->status === MaterialIssueStatus::Draft
            && ! $issue->isReversal()
            && ((int) $issue->issued_by === (int) $actor->id || $actor->hasPermission('issue.confirm'));
    }

    /** Foto pemakaian (A-238): pembuat ISU, pada ISU yang tidak batal. */
    public function attachPhoto(User $actor, MaterialIssue $issue): bool
    {
        return $actor->hasPermission('issue.create') && $issue->status !== MaterialIssueStatus::Cancelled;
    }

    /** Katalog §2.9 `draft → confirmed`; pembalik: mengajukan ke approval. */
    public function confirm(User $actor, MaterialIssue $issue): bool
    {
        return $actor->hasPermission('issue.confirm')
            && $issue->status === MaterialIssueStatus::Draft
            && ! $issue->isAwaitingApproval();
    }

    /** Katalog §2.9 aktor "Pembuat"; pemegang `issue.approve` juga boleh (A-119). */
    public function cancel(User $actor, MaterialIssue $issue): bool
    {
        return $actor->hasPermission('issue.cancel')
            && $issue->status === MaterialIssueStatus::Draft
            && ((int) $issue->issued_by === (int) $actor->id || $actor->hasPermission('issue.approve'));
    }

    public function approve(User $actor, MaterialIssue $issue): bool
    {
        return $actor->hasPermission('issue.approve')
            && $issue->isAwaitingApproval()
            && ! in_array((int) $actor->id, [(int) $issue->issued_by, (int) $issue->submitted_by], true)
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::MaterialIssue, (int) $issue->id, $actor) !== null;
    }

    /** ISU pembalik: izin buat, ISU asal dikonfirmasi dan masih punya baris yang belum dibalik. */
    public function reverse(User $actor, MaterialIssue $issue): bool
    {
        if (! $actor->hasPermission('issue.create')
            || $issue->status !== MaterialIssueStatus::Confirmed
            || $issue->isReversal()) {
            return false;
        }

        $sudah = $issue->reversedLineIds();

        return $issue->lines()->whereNotNull('movement_id')->whereNotIn('id', $sudah ?: [0])->exists();
    }
}
