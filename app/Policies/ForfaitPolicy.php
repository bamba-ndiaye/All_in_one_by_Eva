<?php

namespace App\Policies;

use App\Models\Forfait;
use App\Models\User;

class ForfaitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('services.view');
    }

    public function view(User $user, Forfait $forfait): bool
    {
        return $user->hasPermission('services.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('services.manage');
    }

    public function update(User $user, Forfait $forfait): bool
    {
        return $user->hasPermission('services.manage');
    }
}