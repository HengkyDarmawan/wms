<?php

declare(strict_types=1);

namespace App\Domain\Master\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Client;

class ClientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('client.view');
    }

    public function view(User $actor, Client $client): bool
    {
        return $actor->hasPermission('client.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('client.create');
    }

    public function update(User $actor, Client $client): bool
    {
        return $actor->hasPermission('client.update');
    }

    public function deactivate(User $actor, Client $client): bool
    {
        return $actor->hasPermission('client.deactivate');
    }
}
