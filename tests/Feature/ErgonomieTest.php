<?php

namespace Tests\Feature;

use App\Enums\StatutRendezVous;
use App\Models\Client;
use App\Models\User;
use Tests\Feature\Caisse\CaisseTestCase;

class ErgonomieTest extends CaisseTestCase
{
    // --- Recherche rapide ----------------------------------------------------------

    public function test_recherche_par_nom_et_par_numero(): void
    {
        Client::factory()->create(['prenom' => 'Ndeye', 'nom' => 'Faye', 'telephone' => '+221775551234']);
        Client::factory()->create(['prenom' => 'Ndeye', 'nom' => 'Archivée', 'is_active' => false]);
        Client::factory()->create(['prenom' => 'Oumy', 'nom' => 'Kane']);

        $this->actingAs($this->receptionniste);

        $parNom = $this->getJson(route('recherche.clients', ['q' => 'ndeye']))->assertOk()->json('clients');
        $this->assertSame(['Ndeye Faye', 'Ndeye Archivée'], array_column($parNom, 'nom'));
        $this->assertFalse($parNom[0]['archive']);
        $this->assertTrue($parNom[1]['archive']);
        $this->assertStringContainsString('/clients/', $parNom[0]['url']);

        $parNumero = $this->getJson(route('recherche.clients', ['q' => '77 555']))->json('clients');
        $this->assertSame(['Ndeye Faye'], array_column($parNumero, 'nom'));
    }

    public function test_recherche_trop_courte_ou_invalide(): void
    {
        $this->actingAs($this->receptionniste);

        $this->getJson(route('recherche.clients', ['q' => 'a']))->assertOk()->assertJson(['clients' => []]);
        $this->getJson(route('recherche.clients', ['q' => ['x']]))->assertOk()->assertJson(['clients' => []]);
    }

    public function test_recherche_limitee_a_8_resultats(): void
    {
        Client::factory()->count(12)->create(['prenom' => 'Aïda']);

        $this->actingAs($this->receptionniste)
            ->getJson(route('recherche.clients', ['q' => 'Aïda']))
            ->assertJsonCount(8, 'clients');
    }

    public function test_la_barre_de_recherche_n_apparait_qu_avec_le_droit_clients(): void
    {
        $this->actingAs($this->receptionniste)->get(route('dashboard'))->assertSee('id="recherche-globale"', false);

        $pro = User::factory()->professionnel()->create();
        $this->actingAs($pro)->get(route('dashboard'))->assertDontSee('id="recherche-globale"', false);
        $this->getJson(route('recherche.clients', ['q' => 'Ndeye']))->assertForbidden();
    }

    // --- Encaisser depuis le tableau de bord ---------------------------------------

    public function test_bouton_encaisser_sur_un_rendez_vous_termine_non_paye(): void
    {
        $termine = $this->rdv(StatutRendezVous::Termine, ['date' => '2026-11-09']);
        $paye = $this->rdv(StatutRendezVous::Termine, ['date' => '2026-11-09', 'heure_debut' => '15:00:00', 'heure_fin' => '15:45:00']);
        $this->payer($paye, 20000);
        $confirme = $this->rdv(StatutRendezVous::Confirme, ['date' => '2026-11-09', 'heure_debut' => '17:00:00', 'heure_fin' => '17:45:00']);

        $page = $this->actingAs($this->receptionniste)->get(route('dashboard'));

        $page->assertSee(route('paiements.create', ['rendez_vous' => $termine->id]), false)
            ->assertDontSee(route('paiements.create', ['rendez_vous' => $paye->id]), false)
            ->assertDontSee(route('paiements.create', ['rendez_vous' => $confirme->id]), false);
    }

    public function test_pas_de_bouton_encaisser_sans_droit_de_caisse(): void
    {
        $pro = User::factory()->professionnel()->create();
        $this->employe->update(['user_id' => $pro->id]);
        $termine = $this->rdv(StatutRendezVous::Termine, ['date' => '2026-11-09']);

        $this->actingAs($pro)
            ->get(route('dashboard'))
            ->assertSee($this->client->nomComplet())
            ->assertDontSee(route('paiements.create', ['rendez_vous' => $termine->id]), false);
    }
}
