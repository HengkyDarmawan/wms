<?php

declare(strict_types=1);

namespace App\Domain\Waste\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;

/**
 * Permission: `waste.approve` — `pending_approval → approved / rejected`
 * (Katalog §2.14) dari layar detail. Keputusan lewat mesin approval; pengaju
 * tidak memutus (BR-APR-03); menolak menuntut Alasan `*` (BR-GEN-11).
 */
class ApproveWasteDisposal
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(WasteDisposal $wst, ?User $actor = null, ?string $comment = null): WasteDisposal
    {
        $actor = $this->pastikan($wst, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::WasteDisposal, (int) $wst->id, $actor, $comment);

        return $wst->refresh();
    }

    public function reject(WasteDisposal $wst, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): WasteDisposal
    {
        $actor = $this->pastikan($wst, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw WasteRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::WasteDisposal, (int) $wst->id, $actor, $reasonCodeId, $keterangan);

        return $wst->refresh();
    }

    private function pastikan(WasteDisposal $wst, ?User $actor): User
    {
        if (! $wst->isAwaitingApproval()) {
            throw WasteRuleException::rule('BR-GEN-01', 'Hanya BA waste yang sedang menunggu approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw WasteRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if ((int) $actor->id === (int) $wst->submitted_by) {
            throw WasteRuleException::rule('BR-APR-03', 'Pengaju tidak boleh menyetujui atau menolak BA waste-nya sendiri.');
        }

        return $actor;
    }
}
