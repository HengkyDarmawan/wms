<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Purchasing\Models\VendorPrice;

/** Izin harga beli vendor (purchasing/02 §2, A-216). */
class VendorPricePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('vendor_price.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('vendor_price.manage');
    }

    public function update(User $actor, VendorPrice $price): bool
    {
        return $actor->hasPermission('vendor_price.manage');
    }
}
