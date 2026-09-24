<?php

declare(strict_types=1);

namespace App\Domain\Return\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Models\GoodsReturn;

/**
 * Permission: `return.approve` — memutus tugas approval RET milik pengguna
 * dari layar detail RET (Katalog §2.8).
 *
 * Keputusan dicatat lewat mesin approval (20-approval §3.9); RET baru
 * `approved`/`rejected` setelah keputusan akhir (`GoodsReturnApprovalHandler`).
 * Pengaju tidak boleh memutus dokumennya sendiri (BR-APR-03); menolak menuntut
 * Alasan `*` (BR-GEN-11).
 */
class ApproveGoodsReturn
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(GoodsReturn $ret, ?User $actor = null): GoodsReturn
    {
        $actor = $this->pastikanBisaDiputus($ret, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::GoodsReturn, (int) $ret->id, $actor);

        return $ret->refresh();
    }

    public function reject(GoodsReturn $ret, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): GoodsReturn
    {
        $actor = $this->pastikanBisaDiputus($ret, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw ReturnRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::GoodsReturn, (int) $ret->id, $actor, $reasonCodeId, $keterangan);

        return $ret->refresh();
    }

    private function pastikanBisaDiputus(GoodsReturn $ret, ?User $actor): User
    {
        if ($ret->status !== GoodsReturnStatus::PendingApproval) {
            throw ReturnRuleException::rule('BR-GEN-01', 'Hanya RET berstatus Menunggu Approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw ReturnRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if ((int) $ret->requester_id === (int) $actor->id) {
            throw ReturnRuleException::rule('BR-APR-03', 'Pengaju tidak boleh menyetujui atau menolak RET-nya sendiri.');
        }

        return $actor;
    }
}
