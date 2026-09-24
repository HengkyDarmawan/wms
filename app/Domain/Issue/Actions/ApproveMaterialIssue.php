<?php

declare(strict_types=1);

namespace App\Domain\Issue\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;

/**
 * Permission: `issue.approve` — memutus tugas approval **ISU pembalik** dari
 * layar detail ISU (BR-GEN-04, A-86, A-119).
 *
 * Keputusan dicatat lewat mesin approval; pembalikan terjadi setelah lapis
 * terakhir setuju. Pembuat dan pengaju pembalik tidak boleh memutus
 * (BR-APR-03); menolak menuntut Alasan `*` (BR-GEN-11).
 */
class ApproveMaterialIssue
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(MaterialIssue $issue, ?User $actor = null, ?string $comment = null): MaterialIssue
    {
        $actor = $this->pastikan($issue, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::MaterialIssue, (int) $issue->id, $actor, $comment);

        return $issue->refresh();
    }

    public function reject(MaterialIssue $issue, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialIssue
    {
        $actor = $this->pastikan($issue, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw IssueRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::MaterialIssue, (int) $issue->id, $actor, $reasonCodeId, $keterangan);

        return $issue->refresh();
    }

    private function pastikan(MaterialIssue $issue, ?User $actor): User
    {
        if (! $issue->isAwaitingApproval()) {
            throw IssueRuleException::rule('BR-GEN-01', 'Hanya ISU pembalik yang sedang menunggu approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw IssueRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if (in_array((int) $actor->id, [(int) $issue->issued_by, (int) $issue->submitted_by], true)) {
            throw IssueRuleException::rule('BR-APR-03', 'Pembuat atau pengaju tidak boleh menyetujui atau menolak ISU pembaliknya sendiri.');
        }

        return $actor;
    }
}
