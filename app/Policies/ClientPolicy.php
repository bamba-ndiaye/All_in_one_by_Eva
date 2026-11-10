<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->hasPermission('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasPermission('clients.update');
    }

    /** Archiver ou réactiver. */
    public function archive(User $user, Client $client): bool
    {
        return $user->hasPermission('clients.archive');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('clients.export');
    }
}