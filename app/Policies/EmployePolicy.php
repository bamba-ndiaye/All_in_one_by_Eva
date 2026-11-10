<?php

namespace App\Policies;

use App\Models\Employe;
use App\Models\User;

class EmployePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.view');
    }

    public function view(User $user, Employe $employe): bool
    {
        return $user->hasPermission('staff.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.manage');
    }

    public function update(User $user, Employe $employe): bool
    {
        return $user->hasPermission('staff.manage');
    }

    /** Commissions et performances financières. */
    public function viewEarnings(User $user, Employe $employe): bool
    {
        return $user->hasPermission('staff.view_earnings');
    }
}