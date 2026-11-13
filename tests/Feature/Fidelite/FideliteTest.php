<?php

namespace Tests\Feature\Fidelite;

use App\Enums\TypeMouvementFidelite;
use App\Models\Client;
use App\Models\MouvementFidelite;
use App\Models\Recompense;
use App\Models\User;
use App\Services\FideliteService;
use Database\Seeders\DemoFideliteSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Feature\Caisse\CaisseTestCase;

class FideliteTest extends CaisseTestCase
{
    private Recompense $brushing;

    protected function setUp(): void
    {
        parent::setUp();
        config(['salon.fidelite.fcfa_par_point' => 1000]);

        $this->brushing = Recompense::create(['nom' => 'Brushing offert', 'points' => 15, 'is_active' => true]);
    }

    private function fidelite(): FideliteService
    {
        return app(FideliteService::class);
    }

    private function solde(?Client $client = null): int
    {
        return ($client ?? $this->client)->pointsFidelite();
    }

    // --- Gain automatique ----------------------------------------------------------

    public function test_un_encaissement_rapporte_des_points(): void
    {
        $paiement = $this->payer($this->rdv(), 12500);

        $this->assertSame(12, $this->solde());

        $mouvement = MouvementFidelite::firstOrFail();
        $this->assertSame(TypeMouvementFidelite::Gain, $mouvement->type);
        $this->assertSame($paiement->id, $mouvement->paiement_id);
    }

    public function test_les_paiements_partiels_rapportent_chacun_leurs_points(): void
    {
        $rdv = $this->rdv();
        $this->payer($rdv, 5000);
        $this->payer($rdv, 15000);

        $this->assertSame(20, $this->solde());
    }

    public function test_un_petit_montant_ne_rapporte_rien(): void
    {
        $this->payer($this->rdv(), 900);

        $this->assertSame(0, $this->solde());
        $this->assertSame(0, MouvementFidelite::count());
    }

    public function test_une_vente_libre_rapporte_aussi_des_points(): void
    {
        $this->caisse()->enregistrer([
            'client_id' => $this->client->id,
            'libelle' => 'Shampoing kératine',
            'montant' => 7500,
            'mode_paiement_id' => $this->wave->id,
        ], $this->receptionniste);

        $this->assertSame(7, $this->solde());
    }

    public function test_programme_desactive_aucun_point(): void
    {
        config(['salon.fidelite.fcfa_par_point' => 0]);

        $this->payer($this->rdv(), 20000);

        $this->assertSame(0, $this->solde());
    }

    // --- Annulation et correction de paiement --------------------------------------

    public function test_annuler_un_paiement_retire_ses_points(): void
    {
        $paiement = $this->payer($this->rdv(), 20000);
        $this->assertSame(20, $this->solde());

        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertSame(0, $this->solde());
        $this->assertSame(1, MouvementFidelite::where('type', TypeMouvementFidelite::Retrait->value)->count());
    }

    public function test_le_retrait_reprend_exactement_les_points_gagnes_meme_si_le_taux_change(): void
    {
        $paiement = $this->payer($this->rdv(), 20000);

        config(['salon.fidelite.fcfa_par_point' => 500]);
        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertSame(0, $this->solde());
    }

    public function test_corriger_un_paiement_ajuste_les_points(): void
    {
        $paiement = $this->payer($this->rdv(), 20000);

        $this->caisse()->corriger($paiement, ['montant' => 15000, 'mode_paiement_id' => $this->especes->id], 'Mauvais montant', $this->admin);

        $this->assertSame(15, $this->solde());
    }

    // --- Utilisation d'une récompense ----------------------------------------------

    public function test_la_receptionniste_offre_une_recompense(): void
    {
        $this->payer($this->rdv(), 20000);

        $this->actingAs($this->receptionniste)
            ->post(route('fidelite.utiliser', $this->client), ['recompense_id' => $this->brushing->id])
            ->assertRedirect(route('fidelite.client', $this->client));

        $this->assertSame(5, $this->solde());
        $this->assertDatabaseHas('historique_actions', ['action' => 'fidelite.utilisation', 'sujet_id' => $this->client->id]);
    }

    public function test_points_insuffisants(): void
    {
        $this->payer($this->rdv(), 10000);

        $this->actingAs($this->receptionniste)
            ->post(route('fidelite.utiliser', $this->client), ['recompense_id' => $this->brushing->id])
            ->assertSessionHasErrors('recompense_id');

        $this->assertSame(10, $this->solde());
    }

    public function test_une_recompense_retiree_ne_peut_plus_etre_utilisee(): void
    {
        $this->payer($this->rdv(), 20000);
        $this->brushing->update(['is_active' => false]);

        $this->actingAs($this->receptionniste)
            ->post(route('fidelite.utiliser', $this->client), ['recompense_id' => $this->brushing->id])
            ->assertSessionHasErrors('recompense_id');

        $this->assertSame(20, $this->solde());
    }

    public function test_un_professionnel_ne_peut_pas_offrir_de_recompense(): void
    {
        $this->payer($this->rdv(), 20000);

        $this->expectException(AuthorizationException::class);
        $this->fidelite()->utiliser($this->client, $this->brushing, User::factory()->professionnel()->create());
    }

    // --- Correction manuelle -------------------------------------------------------

    public function test_l_administratrice_corrige_les_points_avec_un_motif(): void
    {
        $this->actingAs($this->admin)
            ->post(route('fidelite.corriger', $this->client), ['points' => '30', 'motif' => 'Geste commercial'])
            ->assertRedirect();
        $this->assertSame(30, $this->solde());

        $this->post(route('fidelite.corriger', $this->client), ['points' => '-10', 'motif' => 'Erreur de saisie'])->assertRedirect();
        $this->assertSame(20, $this->solde());
    }

    public function test_correction_invalide(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('fidelite.corriger', $this->client), ['points' => '0', 'motif' => 'Geste commercial'])->assertSessionHasErrors('points');
        $this->post(route('fidelite.corriger', $this->client), ['points' => '1.5', 'motif' => 'Geste commercial'])->assertSessionHasErrors('points');
        $this->post(route('fidelite.corriger', $this->client), ['points' => '10', 'motif' => 'abc'])->assertSessionHasErrors('motif');
        $this->post(route('fidelite.corriger', $this->client), ['points' => '-5', 'motif' => 'Retrait impossible'])->assertSessionHasErrors('points');

        $this->assertSame(0, $this->solde());
    }

    public function test_la_receptionniste_ne_corrige_pas_les_points(): void
    {
        $this->actingAs($this->receptionniste)
            ->post(route('fidelite.corriger', $this->client), ['points' => '100', 'motif' => 'Je me fais plaisir'])
            ->assertForbidden();

        $this->assertSame(0, $this->solde());
    }

    public function test_l_historique_ne_se_modifie_pas(): void
    {
        $this->payer($this->rdv(), 20000);
        $mouvement = MouvementFidelite::firstOrFail();

        $mouvement->points = 9999;
        $this->assertFalse($mouvement->save());
        $this->assertFalse($mouvement->delete());
        $this->assertSame(20, $this->solde());
    }

    // --- Récompenses ---------------------------------------------------------------

    public function test_l_administratrice_gere_les_recompenses(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('fidelite.recompenses.store'), ['nom' => 'Manucure offerte', 'points' => 80])->assertRedirect();
        $manucure = Recompense::where('nom', 'Manucure offerte')->firstOrFail();

        $this->put(route('fidelite.recompenses.update', $manucure), ['nom' => 'Manucure complète', 'points' => 90])->assertRedirect();
        $this->assertSame(90, $manucure->refresh()->points);

        $this->patch(route('fidelite.recompenses.toggle', $manucure))->assertRedirect();
        $this->assertFalse($manucure->refresh()->is_active);

        $this->post(route('fidelite.recompenses.store'), ['nom' => '', 'points' => 0])->assertSessionHasErrors(['nom', 'points']);
    }

    public function test_la_receptionniste_voit_les_recompenses_sans_les_gerer(): void
    {
        $this->actingAs($this->receptionniste);

        $this->get(route('fidelite.recompenses.index'))->assertOk()->assertSee('Brushing offert')->assertDontSee('Nouvelle récompense');
        $this->get(route('fidelite.recompenses.create'))->assertForbidden();
        $this->post(route('fidelite.recompenses.store'), ['nom' => 'Pirate', 'points' => 1])->assertForbidden();
        $this->patch(route('fidelite.recompenses.toggle', $this->brushing))->assertForbidden();
    }

    // --- Pages ---------------------------------------------------------------------

    public function test_les_pages_s_affichent(): void
    {
        $this->payer($this->rdv(), 20000);
        $sansPoints = Client::factory()->create(['prenom' => 'Khady', 'nom' => 'Sarr']);

        $this->actingAs($this->receptionniste);

        $this->get(route('fidelite.index'))->assertOk()->assertSee($this->client->nomComplet())->assertDontSee('Khady Sarr')->assertSee('Récompense possible');
        $this->get(route('fidelite.index', ['q' => 'Khady']))->assertOk()->assertSee('Khady Sarr');
        $this->get(route('fidelite.index', ['q' => ['x']]))->assertOk();
        $this->get(route('fidelite.client', $this->client))->assertOk()->assertSee('20')->assertSee('Offrir une récompense')->assertDontSee('Corriger les points');
        $this->get(route('fidelite.client', $sansPoints))->assertOk()->assertSee('Aucun point');
        $this->get(route('clients.show', $this->client))->assertOk()->assertSee('Fidélité');
        $this->get(route('fidelite.client', 999999))->assertNotFound();
    }

    public function test_un_professionnel_n_a_pas_acces(): void
    {
        $this->actingAs(User::factory()->professionnel()->create());

        $this->get(route('fidelite.index'))->assertForbidden();
        $this->get(route('fidelite.client', $this->client))->assertForbidden();
    }

    public function test_le_seeder_de_demo_est_rejouable(): void
    {
        Recompense::query()->delete();

        $this->seed(DemoFideliteSeeder::class);
        $this->seed(DemoFideliteSeeder::class);

        $this->assertSame(4, Recompense::count());
    }
}
