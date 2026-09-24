<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;

/**
 * Permission: `adjustment.approve` — memutus tugas approval ADJ milik
 * pengguna dari layar detail ADJ (Katalog §2.12, A-86).
 *
 * Keputusan dicatat lewat mesin approval; ADJ baru `approved` (lalu langsung
 * `posted`) setelah lapis terakhir setuju. Pengaju tidak boleh memutus
 * dokumennya sendiri (BR-APR-03); menolak menuntut Alasan `*` (BR-GEN-11).
 */
class ApproveStockAdjustment
{
    public function __construct(private readonly DecideApproval $keputusan) {}

    public function approve(StockAdjustment $adj, ?User $actor = null, ?string $comment = null): StockAdjustment
    {
        $actor = $this->pastikan($adj, $actor);

        $this->keputusan->approveDocument(ApprovalDocumentType::StockAdjustment, (int) $adj->id, $actor, $comment);

        return $adj->refresh();
    }

    public function reject(StockAdjustment $adj, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): StockAdjustment
    {
        $actor = $this->pastikan($adj, $actor);

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Reject->value)->exists();

        if (! $valid) {
            throw AdjustmentRuleException::field('BR-GEN-11', 'reason', 'Alasan penolakan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $this->keputusan->rejectDocument(ApprovalDocumentType::StockAdjustment, (int) $adj->id, $actor, $reasonCodeId, $keterangan);

        return $adj->refresh();
    }

    private function pastikan(StockAdjustment $adj, ?User $actor): User
    {
        if ($adj->status !== StockAdjustmentStatus::PendingApproval) {
            throw AdjustmentRuleException::rule('BR-GEN-01', 'Hanya ADJ berstatus Menunggu Approval yang bisa diputus.');
        }

        if ($actor === null) {
            throw AdjustmentRuleException::rule('BR-APR-01', 'Keputusan approval harus dicatat atas nama approver.');
        }

        if ((int) $adj->submitted_by === (int) $actor->id) {
            throw AdjustmentRuleException::rule('BR-APR-03', 'Pengaju tidak boleh menyetujui atau menolak ADJ-nya sendiri.');
        }

        return $actor;
    }
}
