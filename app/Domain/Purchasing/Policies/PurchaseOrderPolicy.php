<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;

/**
 * Izin Purchase Order (purchasing/02 §2, Katalog §2.17, A-216). Cakupan gudang
 * tujuan lewat global scope model (di luar cakupan = 404).
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('po.view');
    }

    public function view(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('po.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('po.create');
    }

    public function update(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('po.create') && $po->status === PurchaseOrderStatus::Draft;
    }

    public function submit(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('po.submit') && $po->status === PurchaseOrderStatus::Draft;
    }

    /** Pemisahan tugas: pembuat dan pengaju tidak memutus PO-nya sendiri (BR-APR-03). */
    public function approve(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('po.approve')
            && $po->isAwaitingApproval()
            && ! in_array((int) $actor->id, array_filter([(int) $po->created_by, (int) $po->submitted_by]), true)
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::PurchaseOrder, (int) $po->id, $actor) !== null;
    }

    public function updateEta(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('po.create') && $po->status->isOpen();
    }

    public function cancel(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('po.cancel') && $po->status->isCancellable() && ! $po->hasReceipts();
    }

    public function close(User $actor, PurchaseOrder $po): bool
    {
        return $actor->hasPermission('po.close') && $po->status === PurchaseOrderStatus::PartiallyFulfilled;
    }
}
