<?php

declare(strict_types=1);

namespace App\Domain\Stock\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Stock\Models\StockReservation;

class ReservationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('reservation.view');
    }

    public function view(User $actor, StockReservation $reservation): bool
    {
        return $actor->hasPermission('reservation.view')
            && $actor->canAccessWarehouse((int) $reservation->warehouse_id);
    }

    /** BR-STK-05: pelepasan manual, selalu dengan alasan. */
    public function release(User $actor, StockReservation $reservation): bool
    {
        return $actor->hasPermission('reservation.release')
            && $actor->canAccessWarehouse((int) $reservation->warehouse_id);
    }
}
