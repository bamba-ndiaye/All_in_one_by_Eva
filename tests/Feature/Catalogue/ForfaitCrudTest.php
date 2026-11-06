<?php

namespace Tests\Feature\Catalogue;

use App\Models\Forfait;
use App\Models\HistoriqueAction;
use App\Models\Service;
use App\Models\User;
use App\Support\Fcfa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForfaitCrudTest extends TestCase
{
    use RefreshDatabase;

    private Service $manucure;
    private Service $pedicure;
    private Service $massage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->manucure = Service::factory()->create(['nom' => 'Manucure test', 'prix' => 5000, 'duree_minutes' => 45]);
        $this->pedicure = Service::factory()->create(['nom' => 'Pedicure test', 'prix' => 6000, 'duree_minutes' => 60]);
        $this->massage = Service::factory()->create(['nom' => 'Massage test', 'prix' => 15000, 'duree_minutes' => 60]);
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'nom' => 'Forfait mains et pieds',
            'description' => 'Description',
            'prix' => 10000,
            'is_active' => 1,
            'services' => [$this->manucure->id, $this->pedicure->id],
        ], $override);
    }

    private function admin(): User
    {
        return User::factory()->administratrice()->create();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('services.forfaits.index'))->assertRedirect(route('login'));
    }

    public function test_la_liste_affiche_duree_prix_et_economie(): void
    {
        $forfait = Forfait::factory()->create(['nom' => 'Forfait liste', 'prix' => 10000]);
        $forfait->services()->sync([$this->manucure->id, $this->pedicure->id]);

        foreach (['administratrice', 'receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('services.forfaits.index'))
                ->assertOk()
                ->assertSee('Forfait liste')
                ->assertSee('1 h 45')
                ->assertSee(Fcfa::format(10000))
                ->assertSee(Fcfa::format(1000));
        }
    }

    public function test_les_roles_sans_droit_ne_peuvent_pas_creer_ni_modifier(): void
    {
        $forfait = Forfait::factory()->create();
        $forfait->services()->sync([$this->manucure->id, $this->pedicure->id]);

        foreach (['receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('services.forfaits.create'))->assertForbidden();
            $this->post(route('services.forfaits.store'), $this->data())->assertForbidden();
            $this->get(route('services.forfaits.edit', $forfait))->assertForbidden();
            $this->put(route('services.forfaits.update', $forfait), $this->data(['nom' => 'Autre']))->assertForbidden();
            $this->patch(route('services.forfaits.toggle', $forfait))->assertForbidden();
        }

        $this->assertSame(1, Forfait::count());
        $this->assertTrue($forfait->fresh()->is_active);
    }

    public function test_l_administratrice_cree_un_forfait_avec_ses_prestations(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('services.forfaits.store'), $this->data())
            ->assertRedirect(route('services.forfaits.index'));

        $forfait = Forfait::firstWhere('nom', 'Forfait mains et pieds');

        $this->assertNotNull($forfait);
        $this->assertSame(10000, $forfait->prix);
        $this->assertSame(2, $forfait->services()->count());
        $this->assertDatabaseHas('historique_actions', [
            'user_id' => $admin->id,
            'action' => 'forfait.cree',
            'sujet_id' => $forfait->id,
        ]);
    }

    public function test_un_forfait_exige_au_moins_deux_prestations_distinctes(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('services.forfaits.store'), $this->data(['services' => [$this->manucure->id]]))
            ->assertSessionHasErrors('services');

        $this->post(route('services.forfaits.store'), $this->data(['services' => []]))
            ->assertSessionHasErrors('services');

        $this->post(route('services.forfaits.store'), $this->data(['services' => [$this->manucure->id, $this->manucure->id]]))
            ->assertSessionHasErrors();

        $this->post(route('services.forfaits.store'), $this->data(['services' => [$this->manucure->id, 9999]]))
            ->assertSessionHasErrors();

        $this->assertSame(0, Forfait::count());
    }

    public function test_le_prix_et_le_nom_sont_valides(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('services.forfaits.store'), $this->data(['prix' => '10000.5']))->assertSessionHasErrors('prix');
        $this->post(route('services.forfaits.store'), $this->data(['prix' => '-5']))->assertSessionHasErrors('prix');
        $this->post(route('services.forfaits.store'), $this->data(['nom' => '']))->assertSessionHasErrors('nom');

        Forfait::factory()->create(['nom' => 'Forfait mains et pieds']);
        $this->post(route('services.forfaits.store'), $this->data())->assertSessionHasErrors('nom');
    }

    public function test_modifier_les_prestations_d_un_forfait_est_journalise(): void
    {
        $forfait = Forfait::factory()->create(['nom' => 'Forfait mains et pieds', 'description' => 'Description', 'prix' => 10000]);
        $forfait->services()->sync([$this->manucure->id, $this->pedicure->id]);

        $this->actingAs($this->admin())
            ->put(route('services.forfaits.update', $forfait), $this->data([
                'prix' => 12000,
                'services' => [$this->manucure->id, $this->massage->id],
            ]))
            ->assertRedirect(route('services.forfaits.index'));

        $forfait = $forfait->fresh();

        $this->assertSame(12000, $forfait->prix);
        $this->assertEqualsCanonicalizing(
            [$this->manucure->id, $this->massage->id],
            $forfait->services()->pluck('services.id')->all(),
        );

        $log = HistoriqueAction::where('action', 'forfait.modifie')->first();

        $this->assertNotNull($log);
        $this->assertEquals(10000, $log->anciennes_valeurs['prix']);
        $this->assertEquals(12000, $log->nouvelles_valeurs['prix']);
        $this->assertEqualsCanonicalizing(
            [$this->manucure->id, $this->pedicure->id],
            $log->anciennes_valeurs['services'],
        );
    }

    public function test_une_modification_sans_changement_n_ecrit_rien_dans_le_journal(): void
    {
        $forfait = Forfait::factory()->create(['nom' => 'Forfait mains et pieds', 'description' => 'Description', 'prix' => 10000]);
        $forfait->services()->sync([$this->manucure->id, $this->pedicure->id]);

        $this->actingAs($this->admin())
            ->put(route('services.forfaits.update', $forfait), $this->data())
            ->assertRedirect(route('services.forfaits.index'));

        $this->assertSame(0, HistoriqueAction::where('action', 'forfait.modifie')->count());
    }

    public function test_on_peut_desactiver_puis_reactiver_un_forfait(): void
    {
        $forfait = Forfait::factory()->create();
        $this->actingAs($this->admin());

        $this->patch(route('services.forfaits.toggle', $forfait))->assertRedirect();
        $this->assertFalse($forfait->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'forfait.desactive', 'sujet_id' => $forfait->id]);

        $this->patch(route('services.forfaits.toggle', $forfait))->assertRedirect();
        $this->assertTrue($forfait->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'forfait.active', 'sujet_id' => $forfait->id]);
    }

    public function test_une_prestation_desactivee_n_est_proposee_que_si_elle_est_deja_dans_le_forfait(): void
    {
        $inactive = Service::factory()->inactif()->create(['nom' => 'Zzz ancienne prestation']);
        $forfait = Forfait::factory()->create();
        $forfait->services()->sync([$this->manucure->id, $inactive->id]);

        $this->actingAs($this->admin());

        $this->get(route('services.forfaits.create'))
            ->assertOk()
            ->assertSee('Manucure test')
            ->assertDontSee('Zzz ancienne prestation');

        $this->get(route('services.forfaits.edit', $forfait))
            ->assertOk()
            ->assertSee('Zzz ancienne prestation');
    }
}