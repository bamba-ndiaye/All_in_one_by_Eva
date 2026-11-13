<?php

namespace App\Policies;

use App\Models\Produit;
use App\Models\User;

class ProduitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock.view') || $user->hasPermission('stock.manage');
    }

    public function view(User $user, Produit $produit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock.manage');
    }

    public function update(User $user, Produit $produit): bool
    {
        return $user->hasPermission('stock.manage');
    }

    /** Entrées, sorties et inventaires. */
    public function mouvement(User $user, Produit $produit): bool
    {
        return $user->hasPermission('stock.manage') && $produit->is_active;
    }

    /** Prix d'achat et valeur du stock. */
    public function voirCouts(User $user): bool
    {
        return $user->hasPermission('stock.manage');
    }
}
