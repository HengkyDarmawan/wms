<?php

declare(strict_types=1);

namespace App\Domain\Approval\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Support\ApprovalEngine;

/**
 * Permission: `<dokumen>.approve` dari Katalog (mis. `request.approve`,
 * `vendor_return.approve`) — menyetujui atau menolak satu tugas approval
 * (A-86). Permission mana yang berlaku dibaca dari penangan jenis dokumennya.
 *
 * Dipakai layar "Tugas approval saya" dan tombol Setujui/Tolak di detail
 * dokumen (lewat `ApproveRequest`, `ApproveVendorReturn`). Pemeriksaan
 * SoD, keputusan pertama menang, dan izin ada di `ApprovalEngine`.
 */
class DecideApproval
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    public function approve(ApprovalTask $task, User $actor, ?string $comment = null): ApprovalSnapshot
    {
        return $this->engine->approve($task, $actor, $this->bersih($comment));
    }

    public function reject(ApprovalTask $task, User $actor, ?int $reasonCodeId, ?string $comment = null): ApprovalSnapshot
    {
        return $this->engine->reject($task, $actor, $reasonCodeId, $comment);
    }

    public function approveDocument(ApprovalDocumentType $type, int $documentId, User $actor, ?string $comment = null): ApprovalSnapshot
    {
        return $this->approve($this->tugasUntuk($type, $documentId, $actor), $actor, $comment);
    }

    public function rejectDocument(ApprovalDocumentType $type, int $documentId, User $actor, ?int $reasonCodeId, ?string $comment = null): ApprovalSnapshot
    {
        return $this->reject($this->tugasUntuk($type, $documentId, $actor), $actor, $reasonCodeId, $comment);
    }

    /** Tugas terbuka milik actor pada snapshot yang sedang menunggu. */
    private function tugasUntuk(ApprovalDocumentType $type, int $documentId, User $actor): ApprovalTask
    {
        $snapshot = $this->engine->pendingSnapshot($type, $documentId);

        if ($snapshot === null) {
            throw ApprovalRuleException::rule('BR-APR-09', 'Dokumen ini tidak sedang menunggu approval.');
        }

        if (in_array((int) $actor->id, $snapshot->requesterIds(), true)) {
            throw ApprovalRuleException::rule('BR-APR-03', 'Pengaju tidak boleh menyetujui atau menolak dokumennya sendiri.');
        }

        $milik = ApprovalTask::query()
            ->where('approval_snapshot_id', $snapshot->id)
            ->where('approver_user_id', $actor->id)
            ->orderByRaw('case when status = ? then 0 else 1 end', [ApprovalTaskStatus::Open->value])
            ->orderByDesc('id')
            ->first();

        if ($milik === null) {
            throw ApprovalRuleException::rule('BR-APR-01', 'Anda tidak ditugaskan pada lapis approval yang sedang berjalan.');
        }

        if ($milik->status !== ApprovalTaskStatus::Open) {
            throw ApprovalRuleException::rule('BR-APR-09', 'Tugas Anda pada dokumen ini sudah diputus atau dialihkan.');
        }

        return $milik;
    }

    private function bersih(?string $teks): ?string
    {
        return $teks !== null && trim($teks) !== '' ? trim($teks) : null;
    }
}
