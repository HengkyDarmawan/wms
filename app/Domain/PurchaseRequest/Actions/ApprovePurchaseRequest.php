<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;

/**
 * Permission: `pr.approve` — `pending_approval → approved / rejected`
 * (Katalog §2.15) dari layar detail. Pembuat dan pengaju tidak memutus
 * (BR-APR-03); menolak menuntut Alasan `*` (BR-GEN-11).
 */
class ApprovePurchaseRequest
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(PurchaseRequest $prq, ?User $actor = null, ?string $comment = null): PurchaseRequest
    {
        $actor = $this->pastikan($prq, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::PurchaseRequest, (int) $prq->id, $actor, $comment);

        return $prq->refresh();
    }

    public function reject(PurchaseRequest $prq, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): PurchaseRequest
    {
        $actor = $this->pastikan($prq, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw PurchaseRequestRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::PurchaseRequest, (int) $prq->id, $actor, $reasonCodeId, $keterangan);

        return $prq->refresh();
    }

    private function pastikan(PurchaseRequest $prq, ?User $actor): User
    {
        if (! $prq->isAwaitingApproval()) {
            throw PurchaseRequestRuleException::rule('BR-GEN-01', 'Hanya PRQ yang sedang menunggu approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw PurchaseRequestRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if (in_array((int) $actor->id, [(int) $prq->created_by, (int) $prq->submitted_by], true)) {
            throw PurchaseRequestRuleException::rule('BR-APR-03', 'Pembuat atau pengaju tidak boleh menyetujui atau menolak PRQ-nya sendiri.');
        }

        return $actor;
    }
}
