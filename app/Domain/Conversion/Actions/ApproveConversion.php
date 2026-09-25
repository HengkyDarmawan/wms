<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;

/**
 * Permission: `conversion.approve` — memutus tugas approval CNV dari layar
 * detail (Katalog §2.10 `pending_approval → completed`, A-153).
 *
 * Keputusan dicatat lewat mesin approval; posting terjadi setelah lapis
 * terakhir setuju. Pembuat dan pengaju tidak boleh memutus (BR-APR-03);
 * menolak menuntut Alasan `*` (BR-GEN-11) dan mengembalikan CNV ke `draft`.
 */
class ApproveConversion
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(Conversion $conversion, ?User $actor = null, ?string $comment = null): Conversion
    {
        $actor = $this->pastikan($conversion, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::Conversion, (int) $conversion->id, $actor, $comment);

        return $conversion->refresh();
    }

    public function reject(Conversion $conversion, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): Conversion
    {
        $actor = $this->pastikan($conversion, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw ConversionRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::Conversion, (int) $conversion->id, $actor, $reasonCodeId, $keterangan);

        return $conversion->refresh();
    }

    private function pastikan(Conversion $conversion, ?User $actor): User
    {
        if (! $conversion->isAwaitingApproval()) {
            throw ConversionRuleException::rule('BR-GEN-01', 'Hanya CNV yang sedang menunggu approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw ConversionRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if (in_array((int) $actor->id, [(int) $conversion->prepared_by, (int) $conversion->submitted_by], true)) {
            throw ConversionRuleException::rule('BR-APR-03', 'Pembuat atau pengaju tidak boleh menyetujui atau menolak CNV-nya sendiri.');
        }

        return $actor;
    }
}
