<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;

/**
 * Izin penyesuaian stok (21-opname-penyesuaian §2). Tombol setujui/tolak hanya
 * untuk pemegang tugas terbuka lapis berjalan, bukan pengaju (BR-APR-03).
 */
class StockAdjustmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('adjustment.view');
    }

    public function view(User $actor, StockAdjustment $adj): bool
    {
        return $actor->hasPermission('adjustment.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('adjustment.create');
    }

    public function approve(User $actor, StockAdjustment $adj): bool
    {
        return $actor->hasPermission('adjustment.approve')
            && $adj->status === StockAdjustmentStatus::PendingApproval
            && (int) $adj->submitted_by !== (int) $actor->id
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::StockAdjustment, (int) $adj->id, $actor) !== null;
    }

    public function cancel(User $actor, StockAdjustment $adj): bool
    {
        return $actor->hasPermission('adjustment.cancel') && $adj->isManual() && $adj->status->isCancellable();
    }

    /** ADJ pembalik: izin buat, ADJ asal sudah diposting dan belum dibalik. */
    public function reverse(User $actor, StockAdjustment $adj): bool
    {
        return $actor->hasPermission('adjustment.create')
            && $adj->status === StockAdjustmentStatus::Posted
            && $adj->reversal_of_id === null
            && $adj->activeReversal() === null;
    }
}
