<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Models\Shipment;

/**
 * Izin surat jalan (15-picking-shipment §2).
 *
 * Klien boleh melihat SJ proyeknya — itulah cara ia tahu barangnya sudah
 * berangkat — tetapi tidak pernah menyentuh statusnya.
 */
class ShipmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('shipment.view');
    }

    public function view(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.view') && $this->dalamJangkauan($actor, $shipment);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('shipment.create');
    }

    public function ship(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.ship')
            && $shipment->status->value === 'prepared'
            && $this->dalamJangkauan($actor, $shipment);
    }

    /** Tautan penerima bertoken (A-41): pemegang `shipment.ship` selama SJ sedang dikirim. */
    public function issueToken(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.ship')
            && $shipment->status->value === 'shipped'
            // SJ jemput diterima gudangnya sendiri, bukan penerima di luar (A-248).
            && ! $shipment->isReturnPickup()
            && $this->dalamJangkauan($actor, $shipment);
    }

    public function confirmDelivery(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.confirm_delivery')
            && $shipment->status->value === 'shipped';
    }

    public function cancel(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.cancel')
            && $shipment->status->isCancellable()
            && $this->dalamJangkauan($actor, $shipment);
    }

    /** Klien hanya menjangkau SJ yang ditujukan ke proyek kliennya. */
    private function dalamJangkauan(User $actor, Shipment $shipment): bool
    {
        if ($actor->client_id === null) {
            return true;
        }

        $shipment->loadMissing('destinationProject');

        return (int) $shipment->destinationProject?->client_id === (int) $actor->client_id;
    }
}
