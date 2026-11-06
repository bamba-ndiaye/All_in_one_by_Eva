<?php

namespace Tests\Feature\Catalogue;

use App\Models\CategorieService;
use App\Models\HistoriqueAction;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;

    private CategorieService $categorie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->categorie = CategorieService::factory()->create(['nom' => 'Coiffure hommes']);
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'categorie_service_id' => $this->categorie->id,
            'nom' => 'Coupe test',
            'description' => 'Description',
            'prix' => 5000,
            'duree_minutes' => 45,
            'zone' => 'mixte',
            'is_active' => 1,
        ], $override);
    }

    private function existingService(array $override = []): Service
    {
        return Service::factory()->create(array_merge([
            'categorie_service_id' => $this->categorie->id,
            'nom' => 'Coupe test',
            'description' => 'Description',
            'prix' => 5000,
            'duree_minutes' => 45,
            'zone' => 'mixte',
            'is_active' => true,
        ], $override));
    }

    private function admin(): User
    {
        return User::factory()->administratrice()->create();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('services.index'))->assertRedirect(route('login'));
    }

    public function test_chaque_role_peut_consulter_la_liste(): void
    {
        $this->existingService();

        foreach (['administratrice', 'receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('services.index'))
                ->assertOk()
                ->assertSee('Coupe test')
                ->assertSee('Coiffure hommes');
        }
    }

    public function test_le_bouton_de_creation_depend_des_droits(): void
    {
        $this->actingAs($this->admin())
            ->get(route('services.index'))
            ->assertSee('Nouvelle prestation');

        $this->actingAs(User::factory()->professionnel()->create())
            ->get(route('services.index'))
            ->assertDontSee('Nouvelle prestation');
    }

    public function test_les_roles_sans_droit_ne_peuvent_rien_modifier(): void
    {
        $service = $this->existingService();

        foreach (['receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('services.create'))->assertForbidden();
            $this->post(route('services.store'), $this->data(['nom' => 'Autre']))->assertForbidden();
            $this->get(route('services.edit', $service))->assertForbidden();
            $this->put(route('services.update', $service), $this->data(['prix' => 9999]))->assertForbidden();
            $this->patch(route('services.toggle', $service))->assertForbidden();
        }

        $this->assertSame(1, Service::count());
        $this->assertSame(5000, $service->fresh()->prix);
        $this->assertTrue($service->fresh()->is_active);
    }

    public function test_l_administratrice_cree_une_prestation_et_l_action_est_journalisee(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('services.store'), $this->data())
            ->assertRedirect(route('services.index'));

        $service = Service::firstWhere('nom', 'Coupe test');

        $this->assertNotNull($service);
        $this->assertSame(5000, $service->prix);
        $this->assertSame(45, $service->duree_minutes);
        $this->assertDatabaseHas('historique_actions', [
            'user_id' => $admin->id,
            'action' => 'service.cree',
            'sujet_id' => $service->id,
        ]);
    }

    public function test_les_donnees_invalides_sont_refusees(): void
    {
        $this->actingAs($this->admin());

        $cas = [
            'prix' => ['5000.5', '-1', 'abc', ''],
            'duree_minutes' => ['0', '4', '721', 'abc'],
            'zone' => ['inconnue', ''],
            'nom' => [''],
            'categorie_service_id' => ['9999', ''],
        ];

        foreach ($cas as $champ => $valeurs) {
            foreach ($valeurs as $valeur) {
                $this->post(route('services.store'), $this->data([$champ => $valeur]))
                    ->assertSessionHasErrors($champ);
            }
        }

        $this->assertSame(0, Service::count());
    }

    public function test_un_nom_deja_utilise_dans_la_categorie_est_refuse(): void
    {
        $this->existingService();
        $this->actingAs($this->admin());

        $this->post(route('services.store'), $this->data())->assertSessionHasErrors('nom');

        $autre = CategorieService::factory()->create();
        $this->post(route('services.store'), $this->data(['categorie_service_id' => $autre->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Service::where('nom', 'Coupe test')->count());
    }

    public function test_un_changement_de_prix_est_journalise_avec_ancienne_et_nouvelle_valeur(): void
    {
        $service = $this->existingService();

        $this->actingAs($this->admin())
            ->put(route('services.update', $service), $this->data(['prix' => 6000]))
            ->assertRedirect(route('services.index'));

        $this->assertSame(6000, $service->fresh()->prix);

        $log = HistoriqueAction::where('action', 'service.modifie')->first();

        $this->assertNotNull($log);
        $this->assertEquals(['prix' => 5000], $log->anciennes_valeurs);
        $this->assertEquals(['prix' => 6000], $log->nouvelles_valeurs);
    }

    public function test_une_modification_sans_changement_n_ecrit_rien_dans_le_journal(): void
    {
        $service = $this->existingService();

        $this->actingAs($this->admin())
            ->put(route('services.update', $service), $this->data())
            ->assertRedirect(route('services.index'));

        $this->assertSame(0, HistoriqueAction::where('action', 'service.modifie')->count());
    }

    public function test_on_peut_desactiver_puis_reactiver_une_prestation(): void
    {
        $service = $this->existingService();
        $this->actingAs($this->admin());

        $this->patch(route('services.toggle', $service))->assertRedirect();
        $this->assertFalse($service->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'service.desactive', 'sujet_id' => $service->id]);

        $this->get(route('services.index'))->assertSee('Désactivée');

        $this->patch(route('services.toggle', $service))->assertRedirect();
        $this->assertTrue($service->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'service.active', 'sujet_id' => $service->id]);
    }

    public function test_il_n_existe_aucune_route_de_suppression(): void
    {
        $this->assertFalse(Route::has('services.destroy'));
        $this->assertFalse(Route::has('services.forfaits.destroy'));
    }

    public function test_les_filtres_de_la_liste_fonctionnent(): void
    {
        $this->existingService(['nom' => 'Coupe test']);
        $this->existingService(['nom' => 'Massage relaxant', 'zone' => 'femmes']);
        $this->existingService(['nom' => 'Ancien soin', 'is_active' => false]);

        $this->actingAs($this->admin());

        $this->get(route('services.index', ['q' => 'Massage']))
            ->assertSee('Massage relaxant')
            ->assertDontSee('Coupe test');

        $this->get(route('services.index', ['zone' => 'femmes']))
            ->assertSee('Massage relaxant')
            ->assertDontSee('Coupe test');

        $this->get(route('services.index', ['statut' => 'actifs']))
            ->assertSee('Coupe test')
            ->assertDontSee('Ancien soin');

        $this->get(route('services.index', ['statut' => 'inactifs']))
            ->assertSee('Ancien soin')
            ->assertDontSee('Coupe test');
    }
}