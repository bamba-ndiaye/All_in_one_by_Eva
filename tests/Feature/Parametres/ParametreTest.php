<?php

namespace Tests\Feature\Parametres;

use App\Models\Client;
use App\Models\Parametre;
use App\Models\User;
use App\Services\AnniversaireService;
use App\Services\AppointmentScheduler;
use App\Services\FideliteService;
use App\Services\ParametreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ParametreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->administratrice()->create();
    }

    private function donnees(array $override = []): array
    {
        return array_merge([
            'salon_nom' => 'Eva Beauté Dakar',
            'salon_adresse' => 'Sacré-Cœur 3, Dakar',
            'salon_telephone' => '+221 77 000 00 00',
            'rdv_pause_minutes' => 10,
            'fidelite_fcfa_par_point' => 500,
            'anniversaire_message' => 'Bon anniversaire {prenom}, {age} ans déjà !',
        ], $override);
    }

    public function test_l_administratrice_modifie_les_parametres_et_ils_s_appliquent(): void
    {
        $this->actingAs($this->admin)
            ->put(route('parametres.update'), $this->donnees())
            ->assertRedirect(route('parametres.index'))
            ->assertSessionHas('success');

        $this->assertSame('Eva Beauté Dakar', config('app.name'));
        $this->assertSame('Sacré-Cœur 3, Dakar', config('salon.recu.adresse'));
        $this->assertSame(10, app(AppointmentScheduler::class)->pause());
        $this->assertSame(50, app(FideliteService::class)->pointsPour(25000));

        $client = Client::factory()->make(['prenom' => 'Awa']);
        $this->assertSame('Bon anniversaire Awa, 30 ans déjà !', app(AnniversaireService::class)->message($client, 30));

        $this->assertSame(6, Parametre::count());
        $this->assertDatabaseHas('historique_actions', ['action' => 'parametres.modifies']);
    }

    public function test_les_parametres_enregistres_sont_rechargés_au_demarrage(): void
    {
        Parametre::create(['cle' => 'salon_nom', 'valeur' => 'Salon Rechargé']);
        Parametre::create(['cle' => 'fidelite_fcfa_par_point', 'valeur' => '2000']);
        Parametre::create(['cle' => 'cle_inconnue', 'valeur' => 'ignorée']);
        Cache::flush();

        app(ParametreService::class)->appliquer();

        $this->assertSame('Salon Rechargé', config('app.name'));
        $this->assertSame(2000, config('salon.fidelite.fcfa_par_point'));
        $this->assertNull(config('cle_inconnue'));
    }

    public function test_seuls_les_reglages_changes_sont_enregistres(): void
    {
        $actuelles = app(ParametreService::class)->valeurs();

        $this->actingAs($this->admin)
            ->put(route('parametres.update'), array_merge($actuelles, ['rdv_pause_minutes' => 15]))
            ->assertRedirect();

        $this->assertSame(['rdv_pause_minutes'], Parametre::pluck('cle')->all());

        $this->put(route('parametres.update'), app(ParametreService::class)->valeurs())
            ->assertSessionHas('success', 'Aucun changement à enregistrer.');
    }

    public function test_validation(): void
    {
        $this->actingAs($this->admin)
            ->put(route('parametres.update'), $this->donnees([
                'salon_nom' => '',
                'salon_telephone' => 'appelez-moi',
                'rdv_pause_minutes' => -5,
                'fidelite_fcfa_par_point' => 'beaucoup',
                'anniversaire_message' => 'court',
            ]))
            ->assertSessionHasErrors(['salon_nom', 'salon_telephone', 'rdv_pause_minutes', 'fidelite_fcfa_par_point', 'anniversaire_message']);

        $this->assertSame(0, Parametre::count());
    }

    public function test_fidelite_a_zero_met_le_programme_en_pause(): void
    {
        $this->actingAs($this->admin)->put(route('parametres.update'), $this->donnees(['fidelite_fcfa_par_point' => 0]));

        $this->assertFalse(app(FideliteService::class)->estActive());
    }

    public function test_adresse_videe_redevient_vide(): void
    {
        $this->actingAs($this->admin)->put(route('parametres.update'), $this->donnees());
        $this->put(route('parametres.update'), $this->donnees(['salon_adresse' => '']));

        $this->assertNull(config('salon.recu.adresse'));
    }

    public function test_seule_l_administratrice_y_a_acces(): void
    {
        $this->actingAs(User::factory()->receptionniste()->create());

        $this->get(route('parametres.index'))->assertForbidden();
        $this->put(route('parametres.update'), $this->donnees())->assertForbidden();
        $this->assertSame(0, Parametre::count());
    }

    public function test_la_page_s_affiche_avec_les_valeurs_actuelles(): void
    {
        config(['salon.recu.adresse' => 'Point E, Dakar']);

        $this->actingAs($this->admin)
            ->get(route('parametres.index'))
            ->assertOk()
            ->assertSee('Point E, Dakar')
            ->assertSee("Message d'anniversaire", false);
    }
}
