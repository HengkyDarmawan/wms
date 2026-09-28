<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryRecipients;

/**
 * Izin surat jalan (15-picking-shipment §2).
 *
 * Klien boleh melihat SJ proyeknya — itulah cara ia tahu barangnya sudah
 * berangkat — dan sejak A-312 mengisi bukti terimanya, tetapi tidak pernah
 * menyentuh status lain. Penerima internal di gudang/proyek tujuan boleh
 * membuka SJ walau gudang asalnya di luar cakupannya.
 */
class ShipmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('shipment.view');
    }

    public function view(User $actor, Shipment $shipment): bool
    {
        if (! $this->dalamJangkauan($actor, $shipment)) {
            return false;
        }

        if ($actor->hasPermission('shipment.view') && ($actor->isClient()
            || $actor->canAccessWarehouse((int) $shipment->warehouse_id)
            || app(DeliveryRecipients::class)->isRecipient($actor, $shipment))) {
            return true;
        }

        // A-312: penerima tanpa `shipment.view` (mis. Pemohon Internal) tetap membuka SJ yang ia terima.
        return $actor->hasPermission('shipment.confirm_delivery')
            && app(DeliveryRecipients::class)->isRecipient($actor, $shipment);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('shipment.create');
    }

    /** A-311: berangkatkan oleh staf/Kepala Gudang asal — driver tidak punya akun. */
    public function ship(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.ship')
            && $shipment->status->value === 'prepared'
            && $this->diGudangAsal($actor, $shipment);
    }

    /** Tautan penerima bertoken (A-41): pemegang `shipment.ship` selama SJ sedang dikirim. */
    public function issueToken(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.ship')
            && $shipment->status->value === 'shipped'
            // SJ jemput diterima gudangnya sendiri, bukan penerima di luar (A-248).
            && ! $shipment->isReturnPickup()
            && $this->diGudangAsal($actor, $shipment);
    }

    /** A-312/A-316: penerima utama atau cadangan Kepala Gudang asal (lihat DeliveryRecipients). */
    public function confirmDelivery(User $actor, Shipment $shipment): bool
    {
        return $shipment->status->value === 'shipped'
            && app(DeliveryRecipients::class)->channelFor($actor, $shipment) !== null;
    }

    public function cancel(User $actor, Shipment $shipment): bool
    {
        return $actor->hasPermission('shipment.cancel')
            && $shipment->status->isCancellable()
            && $this->diGudangAsal($actor, $shipment);
    }

    private function diGudangAsal(User $actor, Shipment $shipment): bool
    {
        return ! $actor->isClient() && $actor->canAccessWarehouse((int) $shipment->warehouse_id);
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
