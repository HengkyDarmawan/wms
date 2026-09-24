<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Models\Transfer;

/**
 * Permission: `transfer.approve` — memutus tugas approval TRF milik pengguna
 * dari layar detail TRF (Katalog §2.7).
 *
 * Keputusan dicatat lewat mesin approval (20-approval §3.9); TRF baru
 * `approved`/`rejected` setelah keputusan akhir (`TransferApprovalHandler`).
 * Pengaju tidak boleh memutus dokumennya sendiri (BR-APR-03); menolak menuntut
 * Alasan `*` (BR-GEN-11).
 */
class ApproveTransfer
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(Transfer $trf, ?User $actor = null): Transfer
    {
        $actor = $this->pastikanBisaDiputus($trf, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::Transfer, (int) $trf->id, $actor);

        return $trf->refresh();
    }

    public function reject(Transfer $trf, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): Transfer
    {
        $actor = $this->pastikanBisaDiputus($trf, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw TransferRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::Transfer, (int) $trf->id, $actor, $reasonCodeId, $keterangan);

        return $trf->refresh();
    }

    private function pastikanBisaDiputus(Transfer $trf, ?User $actor): User
    {
        if ($trf->status !== TransferStatus::PendingApproval) {
            throw TransferRuleException::rule('BR-GEN-01', 'Hanya TRF berstatus Menunggu Approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw TransferRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if ((int) $trf->submitted_by === (int) $actor->id) {
            throw TransferRuleException::rule('BR-APR-03', 'Pengaju tidak boleh menyetujui atau menolak TRF-nya sendiri.');
        }

        return $actor;
    }
}
