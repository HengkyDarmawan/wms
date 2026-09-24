<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;

/**
 * Permission: `request.approve` — memutus tugas approval REQ milik pengguna
 * dari layar detail REQ (Katalog Status §2.1).
 *
 * Sejak modul approval ada, kelas ini tidak lagi menyetujui langsung: ia
 * mencatat keputusan pada lapis yang sedang berjalan lewat mesin approval.
 * Status REQ baru menjadi `approved` — dan reservasi lunak baru dibuat —
 * setelah lapis terakhir setuju (`RequestApprovalHandler::onApproved`).
 * Siapa yang boleh memutus ditentukan aturan approval, bukan role (A-86).
 */
class ApproveRequest
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function handle(MaterialRequest $request, ?User $actor = null): MaterialRequest
    {
        $actor = $this->pastikanBolehMemutus($request, $actor);

        // BR-REQ-05: baris tanpa sumber menahan approval sejak lapis pertama.
        if ($request->lines()->withoutSource()->exists()) {
            throw RequestRuleException::rule(
                'BR-REQ-05',
                'Masih ada baris tanpa gudang sumber atau cara pemenuhan.',
            );
        }

        $this->keputusan->approveDocument(ApprovalDocumentType::MaterialRequest, (int) $request->id, $actor);

        return $request->refresh();
    }

    public function reject(MaterialRequest $request, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialRequest
    {
        $actor = $this->pastikanBolehMemutus($request, $actor);

        if ($reasonCodeId === null) {
            throw RequestRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penolakan wajib dipilih.');
        }

        $this->keputusan->rejectDocument(ApprovalDocumentType::MaterialRequest, (int) $request->id, $actor, $reasonCodeId, $notes);

        return $request->refresh();
    }

    private function pastikanBolehMemutus(MaterialRequest $request, ?User $actor): User
    {
        if ($request->status !== MaterialRequestStatus::PendingApproval) {
            throw RequestRuleException::rule(
                'BR-REQ-05',
                'Hanya REQ yang menunggu persetujuan yang bisa disetujui atau ditolak.',
            );
        }

        // BR-REQ-07: pemisahan tugas — pemohon tidak memutus dokumennya sendiri.
        if ($actor !== null && (int) $request->requester_id === (int) $actor->id) {
            throw RequestRuleException::rule(
                'BR-REQ-07',
                'Pemohon tidak boleh menyetujui permintaannya sendiri.',
            );
        }

        if ($actor === null) {
            throw RequestRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        return $actor;
    }
}
