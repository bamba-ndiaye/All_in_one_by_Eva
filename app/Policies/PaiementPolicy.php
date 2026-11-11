<?php

namespace App\Policies;

use App\Enums\TypePaiement;
use App\Models\Paiement;
use App\Models\User;

class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function view(User $user, Paiement $paiement): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payments.record');
    }

    /** Annuler ou corriger : seulement un paiement, pas déjà annulé. */
    public function annuler(User $user, Paiement $paiement): bool
    {
        return $user->hasPermission('payments.correct')
            && $paiement->type === TypePaiement::Paiement
            && ! $paiement->estAnnule();
    }
}