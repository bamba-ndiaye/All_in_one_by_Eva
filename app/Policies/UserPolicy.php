<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function view(User $user, User $compte): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    /** Le compte d'une administratrice n'est modifiable que par une administratrice. */
    public function update(User $user, User $compte): bool
    {
        return $user->hasPermission('users.manage')
            && ($user->isAdmin() || ! $compte->isAdmin());
    }

    /** Personne ne peut désactiver son propre compte. */
    public function toggle(User $user, User $compte): bool
    {
        return $this->update($user, $compte) && ! $user->is($compte);
    }
}
