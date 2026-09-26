<?php

declare(strict_types=1);

namespace App\Domain\Return\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Enums\ShipmentStatus;

/**
 * Izin retur dari proyek (22-retur-transfer §2). Setujui/tolak hanya untuk
 * pemegang tugas approval terbuka; batal oleh pengaju atau pemegang
 * `return.approve` (Katalog §2.8 kolom Aktor, A-109).
 */
class GoodsReturnPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('return.view');
    }

    public function view(User $actor, GoodsReturn $ret): bool
    {
        return $actor->hasPermission('return.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('return.create');
    }

    public function approve(User $actor, GoodsReturn $ret): bool
    {
        return $actor->hasPermission('return.approve')
            && $ret->status === GoodsReturnStatus::PendingApproval
            && (int) $ret->requester_id !== (int) $actor->id
            && app(ApprovalEngine::class)->openTaskFor(ApprovalDocumentType::GoodsReturn, (int) $ret->id, $actor) !== null;
    }

    public function cancel(User $actor, GoodsReturn $ret): bool
    {
        return $actor->hasPermission('return.cancel')
            && $ret->canBeCancelled()
            && ((int) $ret->requester_id === (int) $actor->id || $actor->hasPermission('return.approve'));
    }

    public function sort(User $actor, GoodsReturn $ret): bool
    {
        return $actor->hasPermission('return.sort') && $ret->status === GoodsReturnStatus::Received;
    }

    /** GRN retur dibuat dari detail RET (receipt.create) saat barang tiba. */
    public function receive(User $actor, GoodsReturn $ret): bool
    {
        return $actor->client_id === null
            && $actor->hasPermission('receipt.create')
            && $ret->status === GoodsReturnStatus::InProgress
            && $ret->activeReceipt() === null
            // A-248: barang jemput diterima setelah SJ jemput tiba dan dikonfirmasi.
            && (! $ret->isPickup() || in_array($ret->returnShipment?->status, [ShipmentStatus::Delivered, ShipmentStatus::PartiallyDelivered], true));
    }

    /** PCK SJ balik dari detail RET bila pembuatan otomatis gagal (A-111). */
    public function createPick(User $actor, GoodsReturn $ret): bool
    {
        return $actor->client_id === null
            && $actor->hasPermission('pick.create')
            && $ret->status === GoodsReturnStatus::Approved
            && ! $ret->self_delivered
            && ! $ret->isPickup()
            && $ret->livePickTask() === null;
    }

    /** SJ jemput dari detail RET (A-248): disusun gudang tujuan. */
    public function createPickup(User $actor, GoodsReturn $ret): bool
    {
        return $actor->client_id === null
            && $actor->hasPermission('shipment.create')
            && $ret->status === GoodsReturnStatus::Approved
            && $ret->isPickup()
            && $ret->return_shipment_id === null
            && $actor->canAccessWarehouse((int) $ret->to_warehouse_id);
    }
}
