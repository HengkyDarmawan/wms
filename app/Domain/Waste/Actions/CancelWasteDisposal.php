<?php

declare(strict_types=1);

namespace App\Domain\Waste\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `waste.cancel` — `submitted`/`pending_approval → cancelled`
 * (Katalog §2.14). Belum ada stok yang bergerak; WST yang menunggu approval
 * ditarik dari mesin approval. Alasan `*` wajib (BR-GEN-11).
 */
class CancelWasteDisposal
{
    public function __construct(private readonly ApprovalEngine $approval) {}

    public function handle(WasteDisposal $wst, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): WasteDisposal
    {
        if (! $wst->status->isCancellable()) {
            throw WasteRuleException::rule('BR-GEN-01', 'BA waste berstatus '.$wst->status->label().' tidak bisa dibatalkan.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw WasteRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null;

        return DB::transaction(function () use ($wst, $reasonCodeId, $keterangan, $actor) {
            $menunggu = $wst->status === WasteDisposalStatus::PendingApproval;

            $wst->forceFill([
                'status' => WasteDisposalStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancelled_at' => now(),
                'notes' => $keterangan ?? $wst->notes,
            ])->save();

            if ($menunggu) {
                $this->approval->withdraw(ApprovalDocumentType::WasteDisposal, (int) $wst->id, 'BA waste dibatalkan.', $actor);
            }

            activity('waste')->performedOn($wst)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
                ->log('BA waste dibatalkan');

            return $wst->refresh();
        });
    }
}
