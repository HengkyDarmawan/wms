<?php

declare(strict_types=1);

namespace App\Domain\Stock\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Stock\Models\StockBalance;

/**
 * Melihat stok. Tidak ada izin "memposting": posting bukan tindakan user
 * melainkan akibat dari dokumen, jadi izinnya melekat pada aksi dokumen.
 */
class StockPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('stock.view');
    }

    public function view(User $actor, StockBalance $balance): bool
    {
        return $actor->hasPermission('stock.view');
    }

    /** BR-STK-15: memajukan tanggal kunci periode. */
    public function lockPeriod(User $actor): bool
    {
        return $actor->hasPermission('stock.lock_period');
    }
}
