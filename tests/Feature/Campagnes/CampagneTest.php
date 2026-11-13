<?php

namespace Tests\Feature\Campagnes;

use App\Enums\CibleCampagne;
use App\Enums\StatutCampagne;
use App\Enums\TypePaiement;
use App\Models\Campagne;
use App\Models\CampagneDestinataire;
use App\Models\Client;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\CampagneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CampagneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00'));
        $this->admin = User::factory()->administratrice()->create();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function cliente(string $prenom, bool $consentement = true, string $inscrite = '2026-01-10', array $override = []): Client
    {
        $client = Client::factory()->create(array_merge([
            'prenom' => $prenom,
            'nom' => 'Diop',
            'consentement_promo' => $consentement,
        ], $override));
        $client->forceFill(['created_at' => Carbon::parse($inscrite)])->save();

        return $client;
    }

    private function service(): CampagneService
    {
        return app(CampagneService::class);
    }

    private function donnees(array $override = []): array
    {
        return array_merge([
            'titre' => 'Promo Tabaski',
            'message' => 'Bonjour {prenom} ! -20 % sur les tresses cette semaine.',
            'cible' => 'toutes',
        ], $override);
    }

    private function prenomsCible(CibleCampagne $cible, ?int $jours = null): array
    {
        return $this->service()->requeteCible($cible, $jours)->orderBy('prenom')->pluck('prenom')->all();
    }

    // --- Publics -------------------------------------------------------------------

    public function test_toutes_seulement_les_clientes_actives_et_consentantes(): void
    {
        $this->cliente('Awa');
        $this->cliente('Fatou', false);
        $this->cliente('Khady', true, '2026-01-10', ['is_active' => false]);

        $this->assertSame(['Awa'], $this->prenomsCible(CibleCampagne::Toutes));
    }

    public function test_absentes_sans_rendez_vous_ni_paiement_recent(): void
    {
        $this->cliente('Absente');
        $rdvRecent = $this->cliente('RdvRecent');
        $paieRecent = $this->cliente('PaieRecent');
        $vieuxRdv = $this->cliente('VieuxRdv');
        $this->cliente('Nouvelle', true, '2026-11-01');

        RendezVous::factory()->create(['client_id' => $rdvRecent->id, 'date' => '2026-11-01']);
        RendezVous::factory()->create(['client_id' => $vieuxRdv->id, 'date' => '2026-07-01']);

        Paiement::create([
            'client_id' => $paieRecent->id, 'type' => TypePaiement::Paiement, 'mode_paiement_id' => ModePaiement::factory()->create()->id,
            'montant' => 5000, 'montant_total' => 5000, 'reste_apres' => 0, 'date' => '2026-10-20', 'libelle' => 'Vente', 'user_id' => $this->admin->id,
        ]);

        $this->assertSame(['Absente', 'VieuxRdv'], $this->prenomsCible(CibleCampagne::Absentes, 60));
    }

    public function test_nouvelles_inscrites_recemment(): void
    {
        $this->cliente('Ancienne', true, '2026-01-10');
        $this->cliente('Recente', true, '2026-11-01');

        $this->assertSame(['Recente'], $this->prenomsCible(CibleCampagne::Nouvelles, 30));
    }

    public function test_apercu_du_nombre_en_direct(): void
    {
        $this->cliente('Awa');
        $this->cliente('Binta');

        $this->actingAs($this->admin)
            ->getJson(route('campagnes.apercu', ['cible' => 'toutes']))
            ->assertOk()
            ->assertJson(['nombre' => 2]);
    }

    // --- Brouillon -----------------------------------------------------------------

    public function test_creer_et_modifier_un_brouillon(): void
    {
        $this->actingAs($this->admin)->post(route('campagnes.store'), $this->donnees())->assertRedirect();

        $campagne = Campagne::firstOrFail();
        $this->assertSame(StatutCampagne::Brouillon, $campagne->statut);
        $this->assertNull($campagne->jours);

        $this->put(route('campagnes.update', $campagne), $this->donnees(['cible' => 'absentes', 'jours' => 90]))->assertRedirect();
        $this->assertSame(CibleCampagne::Absentes, $campagne->refresh()->cible);
        $this->assertSame(90, $campagne->jours);
    }

    public function test_validation(): void
    {
        $this->actingAs($this->admin)
            ->post(route('campagnes.store'), ['titre' => '', 'message' => 'court', 'cible' => 'absentes'])
            ->assertSessionHasErrors(['titre', 'message', 'jours']);

        $this->post(route('campagnes.store'), $this->donnees(['cible' => 'pirate']))->assertSessionHasErrors('cible');
    }

    // --- Lancement et envoi --------------------------------------------------------

    public function test_lancer_fige_la_liste_puis_envoyer_ouvre_whatsapp(): void
    {
        $awa = $this->cliente('Awa', true, '2026-01-10', ['telephone' => '+221771111111']);
        $this->cliente('Binta');

        $campagne = $this->service()->enregistrer(null, [
            'titre' => 'Promo', 'message' => 'Bonjour {prenom} !', 'cible' => CibleCampagne::Toutes, 'jours' => null,
        ], $this->admin);

        $this->actingAs($this->admin)->post(route('campagnes.lancer', $campagne))->assertRedirect();
        $this->assertSame(StatutCampagne::EnCours, $campagne->refresh()->statut);
        $this->assertSame(2, $campagne->destinataires()->count());

        // Une cliente qui arrive après le lancement n'est pas ajoutée.
        $this->cliente('Coumba');
        $this->assertSame(2, $campagne->destinataires()->count());

        // Plus de modification possible.
        $this->put(route('campagnes.update', $campagne), $this->donnees())->assertSessionHasErrors('campagne');
        $this->post(route('campagnes.lancer', $campagne))->assertSessionHasErrors('campagne');

        $dAwa = $campagne->destinataires()->where('client_id', $awa->id)->first();
        $reponse = $this->post(route('campagnes.envoyer', [$campagne, $dAwa]));
        $this->assertSame('https://wa.me/221771111111?text='.rawurlencode('Bonjour Awa !'), $reponse->headers->get('Location'));
        $this->assertNotNull($dAwa->refresh()->envoye_at);
        $this->assertSame(StatutCampagne::EnCours, $campagne->refresh()->statut);

        $dernier = $campagne->destinataires()->whereNull('envoye_at')->first();
        $this->post(route('campagnes.envoyer', [$campagne, $dernier]))->assertRedirect();
        $this->assertSame(StatutCampagne::Terminee, $campagne->refresh()->statut);

        $this->get(route('campagnes.show', $campagne))->assertOk()->assertSee('2 / 2 envoyés');
    }

    public function test_consentement_retire_apres_le_lancement(): void
    {
        $awa = $this->cliente('Awa');
        $campagne = $this->service()->enregistrer(null, ['titre' => 'Promo', 'message' => 'Bonjour {prenom} !', 'cible' => CibleCampagne::Toutes, 'jours' => null], $this->admin);
        $this->service()->lancer($campagne, $this->admin);

        $awa->update(['consentement_promo' => false]);
        $d = $campagne->destinataires()->first();

        $this->actingAs($this->admin)
            ->post(route('campagnes.envoyer', [$campagne, $d]))
            ->assertSessionHasErrors('campagne');

        $this->assertNull($d->refresh()->envoye_at);
        $this->get(route('campagnes.show', $campagne))->assertSee('Ne souhaite plus de messages');
    }

    public function test_lancer_sans_destinataire_est_refuse(): void
    {
        $campagne = $this->service()->enregistrer(null, ['titre' => 'Promo', 'message' => 'Bonjour {prenom} !', 'cible' => CibleCampagne::Toutes, 'jours' => null], $this->admin);

        $this->actingAs($this->admin)->post(route('campagnes.lancer', $campagne))->assertSessionHasErrors('campagne');
        $this->assertTrue($campagne->refresh()->estBrouillon());
    }

    public function test_cloturer_arrete_l_envoi(): void
    {
        $this->cliente('Awa');
        $campagne = $this->service()->enregistrer(null, ['titre' => 'Promo', 'message' => 'Bonjour {prenom} !', 'cible' => CibleCampagne::Toutes, 'jours' => null], $this->admin);
        $this->service()->lancer($campagne, $this->admin);

        $this->actingAs($this->admin)->post(route('campagnes.cloturer', $campagne))->assertRedirect();
        $this->assertSame(StatutCampagne::Terminee, $campagne->refresh()->statut);

        $this->post(route('campagnes.envoyer', [$campagne, $campagne->destinataires()->first()]))->assertSessionHasErrors('campagne');
    }

    public function test_un_destinataire_d_une_autre_campagne_renvoie_404(): void
    {
        $this->cliente('Awa');
        $a = $this->service()->enregistrer(null, ['titre' => 'A', 'message' => 'Bonjour {prenom} !', 'cible' => CibleCampagne::Toutes, 'jours' => null], $this->admin);
        $b = $this->service()->enregistrer(null, ['titre' => 'B', 'message' => 'Bonjour {prenom} !', 'cible' => CibleCampagne::Toutes, 'jours' => null], $this->admin);
        $this->service()->lancer($a, $this->admin);

        $this->actingAs($this->admin)
            ->post(route('campagnes.envoyer', [$b, CampagneDestinataire::firstOrFail()]))
            ->assertNotFound();
    }

    // --- Accès et pages ------------------------------------------------------------

    public function test_la_receptionniste_n_a_pas_acces(): void
    {
        $this->actingAs(User::factory()->receptionniste()->create());

        $this->get(route('campagnes.index'))->assertForbidden();
        $this->post(route('campagnes.store'), $this->donnees())->assertForbidden();
        $this->getJson(route('campagnes.apercu'))->assertForbidden();
    }

    public function test_les_pages_s_affichent(): void
    {
        $this->cliente('Awa');
        $this->actingAs($this->admin);

        $this->get(route('campagnes.index'))->assertOk()->assertSee('Aucune campagne');
        $this->get(route('campagnes.create'))->assertOk();

        $this->post(route('campagnes.store'), $this->donnees());
        $campagne = Campagne::firstOrFail();

        $this->get(route('campagnes.index'))->assertOk()->assertSee('Promo Tabaski')->assertSee('Brouillon');
        $this->get(route('campagnes.show', $campagne))->assertOk()->assertSee('Bonjour Awa !', false)->assertSee('Lancer la campagne');
        $this->get(route('campagnes.edit', $campagne))->assertOk();
        $this->get(route('campagnes.show', 999999))->assertNotFound();
    }
}
