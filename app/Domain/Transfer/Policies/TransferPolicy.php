<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Models\Transfer;

/**
 * Izin transfer (22-retur-transfer §2). Setujui/tolak hanya untuk pemegang
 * tugas approval terbuka (20-approval §13); batal oleh pengaju atau Kepala
 * Gudang (Katalog §2.7 kolom Aktor, A-109).
 */
class TransferPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('transfer.view');
    }

    public function view(User $actor, Transfer $trf): bool
    {
        return $actor->hasPermission('transfer.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('transfer.create');
    }

    public function approve(User $actor, Transfer $trf): bool
    {
        return $actor->hasPermission('transfer.approve')
            && $trf->status === TransferStatus::PendingApproval
            && (int) $trf->submitted_by !== (int) $actor->id
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::Transfer, (int) $trf->id, $actor) !== null;
    }

    /** "Pengaju / Kepala Gudang": pengaju TRF-nya sendiri, pemegang `transfer.approve` semua TRF dalam cakupan. */
    public function cancel(User $actor, Transfer $trf): bool
    {
        return $actor->hasPermission('transfer.cancel')
            && $trf->status->isCancellable()
            && ! $trf->hasLivePickTask()
            && ((int) $trf->submitted_by === (int) $actor->id || $actor->hasPermission('transfer.approve'));
    }

    /** PCK dari detail TRF bila pembuatan otomatis gagal atau PCK dibatalkan (A-107). */
    public function createPick(User $actor, Transfer $trf): bool
    {
        return $actor->hasPermission('pick.create')
            && ! $trf->asset_onsite
            && in_array($trf->status, [TransferStatus::Approved, TransferStatus::InProgress], true)
            && ! $trf->hasLivePickTask();
    }

    /** SJ antar site TRF aset (A-249): disusun gudang pemilik bin On-site proyek asal. */
    public function createSiteShipment(User $actor, Transfer $trf): bool
    {
        return $actor->client_id === null
            && $actor->hasPermission('shipment.create')
            && $trf->asset_onsite
            && $trf->status === TransferStatus::Approved
            && $trf->liveAssetShipment() === null
            && $actor->canAccessWarehouse((int) $trf->from_warehouse_id);
    }
}
