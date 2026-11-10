<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\HistoriqueAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'prenom' => 'Awa',
            'nom' => 'Fall',
            'telephone' => '77 123 45 67',
            'whatsapp' => '',
            'email' => 'awa@example.test',
            'date_naissance' => '1990-05-12',
            'canal_prefere' => 'whatsapp',
            'consentement_promo' => 1,
            'notes' => 'Préfère le matin',
        ], $override);
    }

    /** Client identique à data(), pour tester les modifications. */
    private function existingClient(array $override = []): Client
    {
        return Client::factory()->avecConsentement()->create(array_merge([
            'prenom' => 'Awa',
            'nom' => 'Fall',
            'telephone' => '+221771234567',
            'whatsapp' => null,
            'email' => 'awa@example.test',
            'date_naissance' => '1990-05-12',
            'notes' => 'Préfère le matin',
        ], $override));
    }

    private function admin(): User
    {
        return User::factory()->administratrice()->create();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_le_professionnel_n_a_acces_a_rien(): void
    {
        $client = Client::factory()->create();
        $this->actingAs(User::factory()->professionnel()->create());

        $this->get(route('clients.index'))->assertForbidden();
        $this->get(route('clients.show', $client))->assertForbidden();
        $this->get(route('clients.create'))->assertForbidden();
        $this->post(route('clients.store'), $this->data())->assertForbidden();
        $this->get(route('clients.edit', $client))->assertForbidden();
        $this->put(route('clients.update', $client), $this->data())->assertForbidden();
        $this->patch(route('clients.toggle', $client))->assertForbidden();
        $this->get(route('clients.export'))->assertForbidden();

        $this->assertSame(1, Client::count());
    }

    public function test_la_receptionniste_cree_et_modifie_mais_n_archive_ni_n_exporte(): void
    {
        $this->actingAs(User::factory()->receptionniste()->create());

        $this->post(route('clients.store'), $this->data())->assertRedirect();
        $client = Client::firstWhere('nom', 'Fall');
        $this->assertNotNull($client);

        $this->get(route('clients.index'))->assertOk()->assertSee('Awa Fall')->assertDontSee('Exporter en CSV');
        $this->put(route('clients.update', $client), $this->data(['nom' => 'Diop']))->assertRedirect();
        $this->assertSame('Diop', $client->fresh()->nom);

        $this->patch(route('clients.toggle', $client))->assertForbidden();
        $this->get(route('clients.export'))->assertForbidden();
        $this->assertTrue($client->fresh()->is_active);
    }

    public function test_la_creation_normalise_le_numero_date_le_consentement_et_ne_journalise_aucune_donnee_personnelle(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('clients.store'), $this->data())->assertRedirect();

        $client = Client::firstWhere('nom', 'Fall');

        $this->assertSame('+221771234567', $client->telephone);
        $this->assertTrue($client->consentement_promo);
        $this->assertNotNull($client->date_consentement);
        $this->assertNull($client->date_retrait_consentement);
        $this->assertTrue($client->is_active);

        $log = HistoriqueAction::where('action', 'client.cree')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($client->id, $log->sujet_id);

        $contenu = json_encode([$log->description, $log->anciennes_valeurs, $log->nouvelles_valeurs]);
        $this->assertStringNotContainsString('771234567', $contenu);
        $this->assertStringNotContainsString('1990', $contenu);
        $this->assertStringNotContainsString('awa@example.test', $contenu);
    }

    public function test_un_numero_deja_enregistre_est_refuse_quel_que_soit_son_format(): void
    {
        $this->existingClient();
        $this->actingAs($this->admin());

        foreach (['77 123 45 67', '+221771234567', '221771234567', '00221 77 123 45 67'] as $saisie) {
            $this->post(route('clients.store'), $this->data(['telephone' => $saisie, 'prenom' => 'Autre']))
                ->assertSessionHasErrors('telephone');

            $this->assertStringContainsString('Awa Fall', session('errors')->first('telephone'));
        }

        $this->assertSame(1, Client::count());
    }

    public function test_le_doublon_d_une_fiche_archivee_propose_la_reactivation(): void
    {
        $this->existingClient(['is_active' => false]);
        $this->actingAs($this->admin());

        $this->post(route('clients.store'), $this->data())->assertSessionHasErrors('telephone');

        $this->assertStringContainsString('archivée', session('errors')->first('telephone'));
        $this->assertSame(1, Client::count());
    }

    public function test_les_numeros_invalides_sont_refuses(): void
    {
        $this->actingAs($this->admin());

        foreach (['abc', '123', '+221 12 345 67 89', '76 12 34', '88 123 45 67', '+22112345678901', ''] as $saisie) {
            $this->post(route('clients.store'), $this->data(['telephone' => $saisie]))
                ->assertSessionHasErrors('telephone');
        }

        $this->assertSame(0, Client::count());
    }

    public function test_un_numero_etranger_est_accepte(): void
    {
        $this->actingAs($this->admin())
            ->post(route('clients.store'), $this->data(['telephone' => '+33 6 12 34 56 78']))
            ->assertSessionHasNoErrors();

        $this->assertSame('+33612345678', Client::firstWhere('nom', 'Fall')->telephone);
    }

    public function test_le_numero_whatsapp_identique_au_telephone_n_est_pas_stocke(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('clients.store'), $this->data(['whatsapp' => '+221 77 123 45 67']))->assertSessionHasNoErrors();
        $this->assertNull(Client::firstWhere('nom', 'Fall')->whatsapp);

        $this->post(route('clients.store'), $this->data(['nom' => 'Sy', 'telephone' => '76 111 22 33', 'whatsapp' => '78 999 88 77']))
            ->assertSessionHasNoErrors();
        $this->assertSame('+221789998877', Client::firstWhere('nom', 'Sy')->whatsapp);
    }

    public function test_les_donnees_invalides_sont_refusees(): void
    {
        $this->actingAs($this->admin());

        $cas = [
            [['prenom' => ''], 'prenom'],
            [['nom' => ''], 'nom'],
            [['email' => 'pas-un-email'], 'email'],
            [['whatsapp' => 'abc'], 'whatsapp'],
            [['date_naissance' => now()->addDay()->toDateString()], 'date_naissance'],
            [['date_naissance' => '1800-01-01'], 'date_naissance'],
            [['date_naissance' => 'pas-une-date'], 'date_naissance'],
            [['canal_prefere' => 'pigeon'], 'canal_prefere'],
            [['notes' => Str::repeat('a', 2001)], 'notes'],
        ];

        foreach ($cas as [$override, $champ]) {
            $this->post(route('clients.store'), $this->data($override))->assertSessionHasErrors($champ);
        }

        $this->assertSame(0, Client::count());
    }

    public function test_le_consentement_est_date_et_son_retrait_aussi(): void
    {
        $client = $this->existingClient();
        $this->actingAs($this->admin());

        $this->put(route('clients.update', $client), $this->data(['consentement_promo' => 0]))
            ->assertRedirect(route('clients.show', $client));

        $apresRetrait = $client->fresh();
        $this->assertFalse($apresRetrait->consentement_promo);
        $this->assertNotNull($apresRetrait->date_retrait_consentement);
        $this->assertNotNull($apresRetrait->date_consentement);
        $this->assertDatabaseHas('historique_actions', ['action' => 'client.consentement', 'sujet_id' => $client->id]);

        $this->put(route('clients.update', $client), $this->data(['consentement_promo' => 1]));

        $apresRetour = $client->fresh();
        $this->assertTrue($apresRetour->consentement_promo);
        $this->assertNull($apresRetour->date_retrait_consentement);
    }

    public function test_la_modification_ne_journalise_que_les_noms_des_champs(): void
    {
        $client = $this->existingClient();

        $this->actingAs($this->admin())
            ->put(route('clients.update', $client), $this->data(['nom' => 'Diop']))
            ->assertRedirect(route('clients.show', $client));

        $log = HistoriqueAction::where('action', 'client.modifie')->firstOrFail();

        $this->assertSame(['nom'], $log->nouvelles_valeurs['champs']);
        $this->assertNull($log->anciennes_valeurs);
        $this->assertStringNotContainsString('Diop', json_encode($log->nouvelles_valeurs));
    }

    public function test_une_modification_sans_changement_n_ecrit_rien_dans_le_journal(): void
    {
        $client = $this->existingClient();

        $this->actingAs($this->admin())
            ->put(route('clients.update', $client), $this->data())
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame(0, HistoriqueAction::where('action', 'client.modifie')->count());
        $this->assertSame(0, HistoriqueAction::where('action', 'client.consentement')->count());
    }

    public function test_modifier_un_client_garde_son_propre_numero_mais_pas_celui_d_un_autre(): void
    {
        $client = $this->existingClient();
        Client::factory()->create(['telephone' => '+221761112233']);
        $this->actingAs($this->admin());

        $this->put(route('clients.update', $client), $this->data())->assertSessionHasNoErrors();

        $this->put(route('clients.update', $client), $this->data(['telephone' => '76 111 22 33']))
            ->assertSessionHasErrors('telephone');

        $this->assertSame('+221771234567', $client->fresh()->telephone);
    }

    public function test_seule_l_administratrice_archive_et_reactive(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::factory()->receptionniste()->create())
            ->patch(route('clients.toggle', $client))
            ->assertForbidden();
        $this->assertTrue($client->fresh()->is_active);

        $this->actingAs($this->admin());

        $this->patch(route('clients.toggle', $client))->assertRedirect();
        $this->assertFalse($client->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'client.archive', 'sujet_id' => $client->id]);

        $this->patch(route('clients.toggle', $client))->assertRedirect();
        $this->assertTrue($client->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'client.reactive', 'sujet_id' => $client->id]);
    }

    public function test_les_clients_archives_sont_masques_par_defaut(): void
    {
        Client::factory()->create(['prenom' => 'Aminata', 'nom' => 'Zorro']);
        Client::factory()->inactif()->create(['prenom' => 'Binta', 'nom' => 'Yankee']);
        $this->actingAs($this->admin());

        $this->get(route('clients.index'))->assertSee('Zorro')->assertDontSee('Yankee');
        $this->get(route('clients.index', ['statut' => 'archives']))->assertSee('Yankee')->assertDontSee('Zorro');
        $this->get(route('clients.index', ['statut' => 'tous']))->assertSee('Zorro')->assertSee('Yankee');
    }

    public function test_la_recherche_trouve_par_nom_et_par_numero(): void
    {
        Client::factory()->create(['prenom' => 'Aminata', 'nom' => 'Zorro', 'telephone' => '+221771234567']);
        Client::factory()->create(['prenom' => 'Binta', 'nom' => 'Yankee', 'telephone' => '+221765551122']);
        $this->actingAs($this->admin());

        foreach (['zorro', 'Aminata Zorro', '77 123', '+221 77 123 45 67', '771234567'] as $recherche) {
            $this->get(route('clients.index', ['q' => $recherche]))
                ->assertSee('Zorro')
                ->assertDontSee('Yankee');
        }

        $this->get(route('clients.index', ['q' => '765551']))->assertSee('Yankee')->assertDontSee('Zorro');
        $this->get(route('clients.index', ['q' => 'introuvable']))->assertSee('Aucun client ne correspond');
    }

    public function test_les_filtres_fonctionnent(): void
    {
        Client::factory()->avecConsentement()->create(['prenom' => 'Aminata', 'nom' => 'Zorro']);
        Client::factory()->sansNaissance()->create(['prenom' => 'Binta', 'nom' => 'Yankee']);
        $this->actingAs($this->admin());

        $this->get(route('clients.index', ['consentement' => 'oui']))->assertSee('Zorro')->assertDontSee('Yankee');
        $this->get(route('clients.index', ['consentement' => 'non']))->assertSee('Yankee')->assertDontSee('Zorro');
        $this->get(route('clients.index', ['naissance' => 'manquante']))->assertSee('Yankee')->assertDontSee('Zorro');
        $this->get(route('clients.index', ['canal' => 'sms']))->assertSee('Aucun client ne correspond');
    }

    public function test_une_valeur_de_filtre_inattendue_ne_provoque_pas_d_erreur(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('clients.index', ['q' => ['a'], 'statut' => ['x'], 'canal' => ['y']]))->assertOk();
    }

    public function test_la_liste_est_paginee_par_vingt(): void
    {
        Client::factory()->count(25)->create();
        $this->actingAs($this->admin());

        $this->get(route('clients.index'))
            ->assertOk()
            ->assertViewHas('clients', fn ($page) => $page->count() === 20 && $page->total() === 25);

        $this->get(route('clients.index', ['page' => 2]))
            ->assertViewHas('clients', fn ($page) => $page->count() === 5);
    }

    public function test_la_fiche_affiche_les_informations_et_l_historique_a_venir(): void
    {
        $client = $this->existingClient();

        $this->actingAs(User::factory()->receptionniste()->create())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Awa Fall')
            ->assertSee('+221 77 123 45 67')
            ->assertSee('Consentement donné')
            ->assertSee('dès que ces modules seront en place')
            ->assertDontSee('Archivage');
    }

    public function test_l_export_csv_est_reserve_masque_les_notes_et_neutralise_les_formules(): void
    {
        Client::factory()->create([
            'prenom' => '=cmd', 'nom' => 'Piege', 'telephone' => '+221771234567', 'notes' => 'secret medical',
        ]);
        Client::factory()->inactif()->create(['prenom' => 'Archive', 'nom' => 'Cliente']);

        $this->actingAs(User::factory()->receptionniste()->create())
            ->get(route('clients.export'))
            ->assertForbidden();

        $admin = $this->admin();
        $response = $this->actingAs($admin)->get(route('clients.export'));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Prénom;Nom;Téléphone', $csv);
        $this->assertStringContainsString("'=cmd", $csv);
        $this->assertStringContainsString("'+221 77 123 45 67", $csv);
        $this->assertStringNotContainsString('secret medical', $csv);
        $this->assertStringNotContainsString('Archive;', $csv);

        $this->assertDatabaseHas('historique_actions', ['user_id' => $admin->id, 'action' => 'client.export']);

        $tous = $this->get(route('clients.export', ['statut' => 'tous']))->streamedContent();
        $this->assertStringContainsString('Cliente', $tous);
    }

    public function test_il_n_existe_aucune_route_de_suppression(): void
    {
        $this->assertFalse(Route::has('clients.destroy'));
    }
}