<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;

/**
 * Permission: `po.approve` — `pending_approval → approved / rejected`
 * (Katalog §2.17). Pembuat dan pengaju tidak memutus (BR-APR-03); menolak
 * menuntut Alasan `*` (BR-GEN-11).
 */
class ApprovePurchaseOrder
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(PurchaseOrder $po, ?User $actor = null, ?string $comment = null): PurchaseOrder
    {
        $actor = $this->pastikan($po, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::PurchaseOrder, (int) $po->id, $actor, $comment);

        return $po->refresh();
    }

    public function reject(PurchaseOrder $po, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): PurchaseOrder
    {
        $actor = $this->pastikan($po, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw PurchasingRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::PurchaseOrder, (int) $po->id, $actor, $reasonCodeId, $keterangan);

        return $po->refresh();
    }

    private function pastikan(PurchaseOrder $po, ?User $actor): User
    {
        if (! $po->isAwaitingApproval()) {
            throw PurchasingRuleException::rule('BR-GEN-01', 'Hanya PO yang sedang menunggu approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw PurchasingRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if (in_array((int) $actor->id, [(int) $po->created_by, (int) $po->submitted_by], true)) {
            throw PurchasingRuleException::rule('BR-APR-03', 'Pembuat atau pengaju tidak boleh menyetujui atau menolak PO-nya sendiri.');
        }

        return $actor;
    }
}
