<?php

namespace App\Policies;

use App\Enums\StatutRendezVous;
use App\Models\RendezVous;
use App\Models\User;

class RendezVousPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('appointments.view') || $user->hasPermission('appointments.view_own');
    }

    public function view(User $user, RendezVous $rendezVous): bool
    {
        return $user->hasPermission('appointments.view')
            || ($user->hasPermission('appointments.view_own') && $this->estLeSien($user, $rendezVous));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('appointments.manage');
    }

    /** Modifier ou déplacer. */
    public function update(User $user, RendezVous $rendezVous): bool
    {
        return $user->hasPermission('appointments.manage') && $rendezVous->statut->estModifiable();
    }

    public function changeStatus(User $user, RendezVous $rendezVous, StatutRendezVous $vers): bool
    {
        if (! $rendezVous->statut->peutPasserA($vers)) {
            return false;
        }

        if ($user->hasPermission('appointments.manage')) {
            return true;
        }

        // Le professionnel ne gère que les statuts « terrain » de ses propres rendez-vous.
        return $user->hasPermission('appointments.update_status')
            && $vers->estTerrain()
            && $this->estLeSien($user, $rendezVous);
    }

    private function estLeSien(User $user, RendezVous $rendezVous): bool
    {
        $employe = $user->employe;

        return $employe !== null && (int) $employe->id === (int) $rendezVous->employe_id;
    }
}