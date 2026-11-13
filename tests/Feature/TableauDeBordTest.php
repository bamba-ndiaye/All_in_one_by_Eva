<?php

namespace Tests\Feature;

use App\Enums\StatutRendezVous;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Employe;
use App\Models\Produit;
use App\Models\User;
use App\Support\Fcfa;
use Tests\Feature\Caisse\CaisseTestCase;

/**
 * Date figée par AgendaTestCase : lundi 9 novembre 2026, 8 h.
 */
class TableauDeBordTest extends CaisseTestCase
{
    public function test_l_administratrice_voit_les_finances_et_le_graphique(): void
    {
        $rdv = $this->rdv(StatutRendezVous::Termine, ['date' => '2026-11-09']);
        $this->payer($rdv, 20000);
        Depense::factory()->create(['montant' => 3000, 'date' => '2026-11-05']);

        $reponse = $this->actingAs($this->admin)->get(route('dashboard'));

        $reponse->assertOk()
            ->assertSee('Encaissé ce mois')
            ->assertSee(Fcfa::format(17000), false) // résultat : 20 000 - 3 000
            ->assertSee('data-graphique="recettes-depenses"', false)
            ->assertSee('Voir les chiffres jour par jour')
            ->assertSee("Rendez-vous d'aujourd'hui");

        $serie = $reponse->viewData('serie');
        $this->assertCount(30, $serie);
        $this->assertSame('2026-10-11', $serie[0]['date']);
        $this->assertSame(['date' => '2026-11-09', 'encaisse' => 20000, 'depenses' => 0], array_diff_key(end($serie), ['libelle' => 1]));
        $this->assertSame(3000, collect($serie)->firstWhere('date', '2026-11-05')['depenses']);
    }

    public function test_une_annulation_est_deduite_dans_la_courbe(): void
    {
        $paiement = $this->payer($this->rdv(StatutRendezVous::Termine, ['date' => '2026-11-09']), 20000);
        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $serie = $this->actingAs($this->admin)->get(route('dashboard'))->viewData('serie');

        $this->assertSame(0, end($serie)['encaisse']);
    }

    public function test_la_receptionniste_voit_la_journee_sans_les_finances(): void
    {
        $this->rdv(StatutRendezVous::Confirme, ['date' => '2026-11-09', 'heure_debut' => '10:00:00', 'heure_fin' => '10:45:00']);
        $this->rdv(StatutRendezVous::Termine, ['date' => '2026-11-08']); // impayé d'hier

        $this->actingAs($this->receptionniste)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('10:00')
            ->assertSee($this->client->nomComplet())
            ->assertSee('Caisse du jour')
            ->assertSee('1 rendez-vous impayé')
            ->assertSee('Anniversaires')
            ->assertDontSee('Encaissé ce mois')
            ->assertDontSee('data-graphique', false);
    }

    public function test_les_rendez_vous_annules_n_apparaissent_pas(): void
    {
        $autre = Client::factory()->create(['prenom' => 'Annulée', 'nom' => 'Test']);
        $this->rdv(StatutRendezVous::Annule, ['date' => '2026-11-09', 'client_id' => $autre->id]);

        $this->actingAs($this->receptionniste)->get(route('dashboard'))->assertDontSee('Annulée Test');
    }

    public function test_la_professionnelle_ne_voit_que_ses_rendez_vous(): void
    {
        $pro = User::factory()->professionnel()->create();
        $this->employe->update(['user_id' => $pro->id]);

        $collegue = Employe::factory()->create();
        $autreCliente = Client::factory()->create(['prenom' => 'Cliente', 'nom' => 'DeLaCollegue']);

        $this->rdv(StatutRendezVous::Confirme, ['date' => '2026-11-09']);
        $this->rdv(StatutRendezVous::Confirme, ['date' => '2026-11-09', 'employe_id' => $collegue->id, 'client_id' => $autreCliente->id]);

        $this->actingAs($pro)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("Mes rendez-vous d'aujourd'hui")
            ->assertSee($this->client->nomComplet())
            ->assertDontSee('DeLaCollegue')
            ->assertDontSee('Caisse du jour')
            ->assertDontSee('Stock bas');
    }

    public function test_professionnelle_sans_fiche_employe(): void
    {
        $this->actingAs(User::factory()->professionnel()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("n'est relié à aucune fiche employé", false);
    }

    public function test_alerte_de_stock_bas(): void
    {
        $produit = Produit::factory()->create(['nom' => 'Cire tiède', 'seuil_alerte' => 2]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertSee('Cire tiède')
            ->assertSee('Rupture');

        $this->assertSame(0, $produit->refresh()->quantite);
    }

    public function test_boutons_d_action_selon_les_droits(): void
    {
        $this->actingAs($this->receptionniste)->get(route('dashboard'))
            ->assertSee(route('rendez-vous.create'), false)
            ->assertSee(route('paiements.create'), false)
            ->assertSee(route('clients.create'), false);

        $this->actingAs(User::factory()->professionnel()->create())->get(route('dashboard'))
            ->assertDontSee(route('paiements.create'), false)
            ->assertDontSee(route('clients.create'), false);
    }
}
