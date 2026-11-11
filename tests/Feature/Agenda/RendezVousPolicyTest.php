<?php

namespace Tests\Feature\Agenda;

use App\Enums\StatutRendezVous as S;
use App\Models\Employe;
use App\Models\RendezVous;
use App\Models\User;

class RendezVousPolicyTest extends AgendaTestCase
{
    private function rdv(array $override = []): RendezVous
    {
        return RendezVous::factory()->create(array_merge([
            'employe_id' => $this->employe->id,
            'client_id' => $this->client->id,
        ], $override));
    }

    /** Un professionnel lié à une fiche employé. */
    private function professionnel(): array
    {
        $user = User::factory()->professionnel()->create();
        $employe = Employe::factory()->create(['user_id' => $user->id]);

        return [$user, $employe];
    }

    public function test_la_receptionniste_gere_les_rendez_vous(): void
    {
        $rdv = $this->rdv();
        $user = $this->receptionniste;

        $this->assertTrue($user->can('viewAny', RendezVous::class));
        $this->assertTrue($user->can('view', $rdv));
        $this->assertTrue($user->can('create', RendezVous::class));
        $this->assertTrue($user->can('update', $rdv));
        $this->assertTrue($user->can('changeStatus', [$rdv, S::Annule]));
        $this->assertTrue($user->can('changeStatus', [$rdv, S::ClientArrive]));
        $this->assertFalse($user->can('changeStatus', [$rdv, S::Termine]));   // passage interdit
    }

    public function test_un_rendez_vous_deja_commence_ne_se_modifie_plus(): void
    {
        $this->assertFalse($this->receptionniste->can('update', $this->rdv(['statut' => S::EnCours])));
        $this->assertFalse($this->receptionniste->can('update', $this->rdv(['statut' => S::Termine])));
        $this->assertFalse($this->receptionniste->can('update', $this->rdv(['statut' => S::Annule])));
        $this->assertTrue($this->receptionniste->can('update', $this->rdv(['statut' => S::EnAttente])));
    }

    public function test_le_professionnel_ne_voit_que_ses_rendez_vous(): void
    {
        [$pro, $monEmploye] = $this->professionnel();
        $mien = $this->rdv(['employe_id' => $monEmploye->id]);
        $autre = $this->rdv();

        $this->assertTrue($pro->can('viewAny', RendezVous::class));
        $this->assertTrue($pro->can('view', $mien));
        $this->assertFalse($pro->can('view', $autre));
    }

    public function test_le_professionnel_ne_peut_ni_creer_ni_modifier(): void
    {
        [$pro, $monEmploye] = $this->professionnel();
        $mien = $this->rdv(['employe_id' => $monEmploye->id]);

        $this->assertFalse($pro->can('create', RendezVous::class));
        $this->assertFalse($pro->can('update', $mien));
    }

    public function test_le_professionnel_change_seulement_les_statuts_terrain_des_siens(): void
    {
        [$pro, $monEmploye] = $this->professionnel();
        $mien = $this->rdv(['employe_id' => $monEmploye->id]);
        $autre = $this->rdv();

        $this->assertTrue($pro->can('changeStatus', [$mien, S::ClientArrive]));
        $this->assertTrue($pro->can('changeStatus', [$mien, S::ClientAbsent]));
        $this->assertFalse($pro->can('changeStatus', [$mien, S::Annule]));
        $this->assertFalse($pro->can('changeStatus', [$autre, S::ClientArrive]));

        $enCours = $this->rdv(['employe_id' => $monEmploye->id, 'statut' => S::EnCours]);
        $this->assertTrue($pro->can('changeStatus', [$enCours, S::Termine]));
    }

    public function test_un_professionnel_sans_fiche_employe_ne_voit_rien(): void
    {
        $user = User::factory()->professionnel()->create();

        $this->assertFalse($user->can('view', $this->rdv()));
    }

    public function test_un_compte_desactive_n_a_aucun_droit(): void
    {
        $user = User::factory()->receptionniste()->inactif()->create();
        $rdv = $this->rdv();

        $this->assertFalse($user->can('viewAny', RendezVous::class));
        $this->assertFalse($user->can('view', $rdv));
        $this->assertFalse($user->can('create', RendezVous::class));
    }
}