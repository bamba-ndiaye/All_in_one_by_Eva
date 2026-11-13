<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

class DepensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('expenses.manage');
    }

    public function view(User $user, Depense $depense): bool
    {
        return $user->hasPermission('expenses.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('expenses.manage');
    }

    public function update(User $user, Depense $depense): bool
    {
        return $user->hasPermission('expenses.manage') && ! $depense->estAnnulee();
    }

    public function annuler(User $user, Depense $depense): bool
    {
        return $user->hasPermission('expenses.manage') && ! $depense->estAnnulee();
    }
}