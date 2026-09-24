<?php

declare(strict_types=1);

namespace App\Domain\Count\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\StockCount;

/**
 * Izin sesi opname (21-opname-penyesuaian §2). Tombol setujui/tolak hanya
 * untuk pemegang tugas approval terbuka yang bukan penghitung sesi
 * (BR-OPN-09).
 */
class StockCountPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('count.view');
    }

    public function view(User $actor, StockCount $count): bool
    {
        return $actor->hasPermission('count.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('count.create');
    }

    public function start(User $actor, StockCount $count): bool
    {
        return $actor->hasPermission('count.start') && $count->status === StockCountStatus::Planned;
    }

    public function assign(User $actor, StockCount $count): bool
    {
        return $actor->hasPermission('count.assign') && $count->status->isCounting();
    }

    public function reconcile(User $actor, StockCount $count): bool
    {
        if (! $actor->hasPermission('count.reconcile')) {
            return false;
        }

        if ($count->status->isCounting()) {
            return true;
        }

        return $count->status === StockCountStatus::Reconciling
            && app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::StockCount, (int) $count->id) === null;
    }

    public function approve(User $actor, StockCount $count): bool
    {
        return $actor->hasPermission('count.approve')
            && $count->status === StockCountStatus::Reconciling
            && ! in_array((int) $actor->id, $count->counterIds(), true)
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::StockCount, (int) $count->id, $actor) !== null;
    }

    public function cancel(User $actor, StockCount $count): bool
    {
        return $actor->hasPermission('count.cancel') && $count->status === StockCountStatus::Planned;
    }

    /** Laporan PDF terbit setelah sesi disetujui/ditutup. */
    public function report(User $actor, StockCount $count): bool
    {
        return $actor->hasPermission('count.view')
            && in_array($count->status, [StockCountStatus::Approved, StockCountStatus::Closed], true);
    }
}
