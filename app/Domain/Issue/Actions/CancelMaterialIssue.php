<?php

declare(strict_types=1);

namespace App\Domain\Issue\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `issue.cancel` — `draft → cancelled` (Katalog §2.9). Belum ada
 * stok yang bergerak, jadi tidak ada pembalik. ISU `confirmed` tidak bisa
 * dibatalkan; koreksinya ISU pembalik (BR-GEN-04). ISU pembalik yang sedang
 * menunggu approval ikut ditarik dari mesin approval.
 *
 * Alasan `*` wajib, Keterangan opsional (BR-GEN-11).
 */
class CancelMaterialIssue
{
    public function __construct(private readonly ApprovalEngine $approval) {}

    public function handle(MaterialIssue $issue, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialIssue
    {
        if ($issue->status !== MaterialIssueStatus::Draft) {
            throw IssueRuleException::rule('BR-GEN-04', 'ISU berstatus '.$issue->status->label().' tidak bisa dibatalkan; koreksi pemakaian yang sudah dikonfirmasi lewat ISU pembalik.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw IssueRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null;

        return DB::transaction(function () use ($issue, $reasonCodeId, $keterangan, $actor) {
            $issue->forceFill([
                'status' => MaterialIssueStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancelled_at' => now(),
                'notes' => $keterangan ?? $issue->notes,
            ])->save();

            if ($issue->isReversal()) {
                $this->approval->withdraw(ApprovalDocumentType::MaterialIssue, (int) $issue->id, 'ISU pembalik dibatalkan.', $actor);
            }

            activity('issue')->performedOn($issue)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
                ->log('ISU dibatalkan');

            return $issue->refresh();
        });
    }
}
