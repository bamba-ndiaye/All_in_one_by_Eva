<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $user->hasPermission('services.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('services.manage');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->hasPermission('services.manage');
    }
}