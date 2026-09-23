<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;

/** Izin selisih pengiriman (15-picking-shipment §2, BR-SJ-10). */
class DiscrepancyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('discrepancy.view');
    }

    public function view(User $actor, DeliveryDiscrepancy $dsc): bool
    {
        return $actor->hasPermission('discrepancy.view');
    }

    /** Menentukan nasib barang adalah keputusan Kepala Gudang, bukan staf. */
    public function resolve(User $actor, DeliveryDiscrepancy $dsc): bool
    {
        return $actor->hasPermission('discrepancy.resolve') && $dsc->status->value === 'open';
    }
}
