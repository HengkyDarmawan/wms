<?php

declare(strict_types=1);

namespace App\Domain\Stock\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Stock\Models\StockEvent;

/** Outbox kejadian adalah jejak integrasi; hanya dibaca, tidak pernah diubah. */
class StockEventPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('stock_event.view');
    }

    public function view(User $actor, StockEvent $event): bool
    {
        return $actor->hasPermission('stock_event.view');
    }
}
