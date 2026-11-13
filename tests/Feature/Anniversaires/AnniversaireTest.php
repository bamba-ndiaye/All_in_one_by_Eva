<?php

namespace Tests\Feature\Anniversaires;

use App\Models\Client;
use App\Models\User;
use App\Models\VoeuAnniversaire;
use App\Services\AnniversaireService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnniversaireTest extends TestCase
{
    use RefreshDatabase;

    private User $receptionniste;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00'));

        $this->receptionniste = User::factory()->receptionniste()->create();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function cliente(string $prenom, ?string $naissance, bool $consentement = true, array $override = []): Client
    {
        return Client::factory()->create(array_merge([
            'prenom' => $prenom,
            'nom' => 'Ndiaye',
            'date_naissance' => $naissance,
            'consentement_promo' => $consentement,
        ], $override));
    }

    private function service(): AnniversaireService
    {
        return app(AnniversaireService::class);
    }

    public function test_liste_triee_par_proximite_avec_age(): void
    {
        $this->cliente('Awa', '1990-11-13');      // aujourd'hui, 36 ans
        $this->cliente('Fatou', '1985-11-15');    // dans 2 jours
        $this->cliente('Khady', '2000-12-05');    // dans 22 jours
        $this->cliente('Mariama', '1995-12-20');  // dans 37 jours : hors liste
        $this->cliente('Ndeye', '1992-11-10');    // passé il y a 3 jours : l'an prochain
        $this->cliente('Sans', null);
        $this->cliente('Archivee', '1990-11-14', true, ['is_active' => false]);

        $liste = $this->service()->prochains(30);

        $this->assertSame(['Awa', 'Fatou', 'Khady'], $liste->map(fn ($a) => $a['client']->prenom)->all());
        $this->assertSame([0, 2, 22], $liste->pluck('dans')->all());
        $this->assertSame([36, 41, 26], $liste->pluck('age')->all());
    }

    public function test_fin_d_annee_l_anniversaire_de_janvier_est_bien_l_an_prochain(): void
    {
        $this->travelTo(Carbon::parse('2026-12-20 09:00:00'));
        $this->cliente('Binta', '1999-01-03');

        $a = $this->service()->prochains(30)->first();

        $this->assertSame('2027-01-03', $a['date']->toDateString());
        $this->assertSame(14, $a['dans']);
        $this->assertSame(28, $a['age']);
    }

    public function test_ne_le_29_fevrier_fete_le_28_les_annees_non_bissextiles(): void
    {
        $client = $this->cliente('Coumba', '2000-02-29');

        $this->assertSame('2027-02-28', $this->service()->prochaineDate($client, CarbonImmutable::parse('2026-11-13'))->toDateString());
        $this->assertSame('2028-02-29', $this->service()->prochaineDate($client, CarbonImmutable::parse('2027-03-01'))->toDateString());
    }

    public function test_message_et_lien_whatsapp(): void
    {
        config(['salon.anniversaires.message' => 'Bonne fête {prenom}, {age} ans !']);
        $client = $this->cliente('Awa', '1990-11-13', true, ['telephone' => '+221771234567']);

        $this->assertSame('Bonne fête Awa, 36 ans !', $this->service()->message($client, 36));
        $this->assertSame(
            'https://wa.me/221771234567?text='.rawurlencode('Bonne fête Awa, 36 ans !'),
            $this->service()->lienWhatsapp($client, 36),
        );
    }

    public function test_souhaiter_ouvre_whatsapp_et_note_les_voeux_une_seule_fois(): void
    {
        $client = $this->cliente('Awa', '1990-11-13');

        $this->actingAs($this->receptionniste);

        $reponse = $this->post(route('anniversaires.souhaiter', $client));
        $reponse->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/'.ltrim($client->telephone, '+'), $reponse->headers->get('Location'));

        $this->post(route('anniversaires.souhaiter', $client))->assertRedirect();

        $this->assertSame(1, VoeuAnniversaire::count());
        $this->assertSame(2026, VoeuAnniversaire::first()->annee);
        $this->assertDatabaseHas('historique_actions', ['action' => 'anniversaire.souhaite', 'sujet_id' => $client->id]);

        $this->get(route('anniversaires.index'))->assertOk()->assertSee('Déjà souhaité')->assertDontSee(route('anniversaires.souhaiter', $client));
    }

    public function test_sans_consentement_pas_de_message(): void
    {
        $client = $this->cliente('Fatou', '1985-11-13', false);

        $this->actingAs($this->receptionniste);

        $this->get(route('anniversaires.index'))->assertOk()->assertSee('Fatou')->assertSee('Pas de consentement')->assertDontSee(route('anniversaires.souhaiter', $client));
        $this->post(route('anniversaires.souhaiter', $client))->assertForbidden();
        $this->assertSame(0, VoeuAnniversaire::count());
    }

    public function test_un_professionnel_n_a_pas_acces(): void
    {
        $client = $this->cliente('Awa', '1990-11-13');

        $this->actingAs(User::factory()->professionnel()->create());

        $this->get(route('anniversaires.index'))->assertForbidden();
        $this->post(route('anniversaires.souhaiter', $client))->assertForbidden();
    }

    public function test_sans_droit_messages_on_voit_sans_pouvoir_souhaiter(): void
    {
        $client = $this->cliente('Awa', '1990-11-13');
        $user = User::factory()->professionnel()->create();
        $user->grantPermission('birthdays.view');

        $this->actingAs($user);

        $this->get(route('anniversaires.index'))->assertOk()->assertSee('Awa')->assertDontSee(route('anniversaires.souhaiter', $client));
        $this->post(route('anniversaires.souhaiter', $client))->assertForbidden();
    }

    public function test_page_sections_et_rappel_des_dates_manquantes(): void
    {
        $this->cliente('Awa', '1990-11-13');
        $this->cliente('Fatou', '1985-11-15');
        $this->cliente('Khady', '2000-12-05');
        $this->cliente('Sans', null);

        $this->actingAs($this->receptionniste)
            ->get(route('anniversaires.index'))
            ->assertOk()
            ->assertSeeInOrder(["Aujourd'hui", 'Awa', 'Cette semaine', 'Fatou', 'Plus tard ce mois-ci', 'Khady'])
            ->assertSee('1 cliente n&#039;a pas de date de naissance', false)
            ->assertSee(route('clients.index', ['naissance' => 'manquante']), false);
    }

    public function test_liste_vide(): void
    {
        $this->actingAs($this->receptionniste)
            ->get(route('anniversaires.index'))
            ->assertOk()
            ->assertSee("Aucun anniversaire aujourd'hui.");
    }
}
