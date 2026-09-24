<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Models\VendorReturn;

/**
 * Izin retur ke vendor (19-receipt-putaway §2). Pengaju tidak melihat tombol
 * setujui/tolak pada RTV-nya sendiri (BR-APR-03).
 */
class VendorReturnPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('vendor_return.view');
    }

    public function view(User $actor, VendorReturn $rtv): bool
    {
        return $actor->hasPermission('vendor_return.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('vendor_return.create');
    }

    public function approve(User $actor, VendorReturn $rtv): bool
    {
        // Hanya approver yang memegang tugas terbuka lapis berjalan (20-approval §13).
        return $actor->hasPermission('vendor_return.approve')
            && $rtv->status === VendorReturnStatus::PendingApproval
            && (int) $rtv->submitted_by !== (int) $actor->id
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::VendorReturn, (int) $rtv->id, $actor) !== null;
    }

    public function ship(User $actor, VendorReturn $rtv): bool
    {
        return $actor->hasPermission('vendor_return.ship') && $rtv->status === VendorReturnStatus::Approved;
    }

    public function complete(User $actor, VendorReturn $rtv): bool
    {
        return $actor->hasPermission('vendor_return.complete') && $rtv->status === VendorReturnStatus::Shipped;
    }

    public function cancel(User $actor, VendorReturn $rtv): bool
    {
        return $actor->hasPermission('vendor_return.cancel') && $rtv->status->isCancellable();
    }
}
