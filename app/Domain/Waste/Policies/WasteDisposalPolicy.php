<?php

declare(strict_types=1);

namespace App\Domain\Waste\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Models\WasteDisposal;

/**
 * Izin Berita Acara Waste (24-konversi-waste §2, A-158). Cakupan gudang/proyek
 * lewat global scope model (di luar cakupan = 404).
 */
class WasteDisposalPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('waste.view');
    }

    public function view(User $actor, WasteDisposal $wst): bool
    {
        return $actor->hasPermission('waste.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('waste.create');
    }

    public function approve(User $actor, WasteDisposal $wst): bool
    {
        return $actor->hasPermission('waste.approve')
            && $wst->isAwaitingApproval()
            && (int) $actor->id !== (int) $wst->submitted_by
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::WasteDisposal, (int) $wst->id, $actor) !== null;
    }

    public function close(User $actor, WasteDisposal $wst): bool
    {
        return $actor->hasPermission('waste.close')
            && $wst->status === WasteDisposalStatus::Approved;
    }

    /** Katalog §2.14 aktor "Pengaju"; pemegang `waste.approve` juga boleh (A-158). */
    public function cancel(User $actor, WasteDisposal $wst): bool
    {
        return $actor->hasPermission('waste.cancel')
            && $wst->status->isCancellable()
            && ((int) $wst->submitted_by === (int) $actor->id || $actor->hasPermission('waste.approve'));
    }
}
