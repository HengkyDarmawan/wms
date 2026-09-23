<?php

declare(strict_types=1);

namespace App\Domain\Master\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('vendor.view');
    }

    public function view(User $actor, Vendor $vendor): bool
    {
        return $actor->hasPermission('vendor.view');
    }

    /** A-53: Penindak Lanjut PR boleh membuat vendor sementara saat memesan. */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('vendor.create');
    }

    public function update(User $actor, Vendor $vendor): bool
    {
        return $actor->hasPermission('vendor.update');
    }

    public function deactivate(User $actor, Vendor $vendor): bool
    {
        return $actor->hasPermission('vendor.deactivate');
    }
}
