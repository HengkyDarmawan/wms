<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;

/**
 * Izin Purchase Request (26-purchase-request §2, Katalog §2.15). Cakupan gudang
 * tujuan lewat global scope model (di luar cakupan = 404).
 */
class PurchaseRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('pr.view');
    }

    public function view(User $actor, PurchaseRequest $prq): bool
    {
        return $actor->hasPermission('pr.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('pr.create');
    }

    /** Draf titik pesan ulang ditinjau pemegang `pr.submit` sebelum diajukan (BR-REQ-11). */
    public function update(User $actor, PurchaseRequest $prq): bool
    {
        return $actor->hasPermission('pr.submit') && $prq->status === PurchaseRequestStatus::Draft;
    }

    public function submit(User $actor, PurchaseRequest $prq): bool
    {
        return $this->update($actor, $prq) && $prq->lines()->exists();
    }

    /** Pemisahan tugas: pembuat dan pengaju tidak menyetujui PRQ-nya sendiri (BR-APR-03). */
    public function approve(User $actor, PurchaseRequest $prq): bool
    {
        return $actor->hasPermission('pr.approve')
            && $prq->isAwaitingApproval()
            && ! in_array((int) $actor->id, array_filter([(int) $prq->created_by, (int) $prq->submitted_by]), true)
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::PurchaseRequest, (int) $prq->id, $actor) !== null;
    }

    /** Catatan pemesanan (A-51): PRQ disetujui atau sudah berjalan. */
    public function order(User $actor, PurchaseRequest $prq): bool
    {
        return $actor->hasPermission('pr.order') && $prq->status->acceptsOrders();
    }

    public function cancel(User $actor, PurchaseRequest $prq): bool
    {
        return $actor->hasPermission('pr.cancel') && $prq->status->isCancellable() && ! $prq->hasReceipts();
    }
}
