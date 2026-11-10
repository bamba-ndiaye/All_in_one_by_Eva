<?php

namespace Tests\Feature\Equipe;

use App\Enums\TypeCommission;
use App\Models\Employe;
use App\Models\HistoriqueAction;
use App\Models\Service;
use App\Models\User;
use App\Support\Fcfa;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmployeCrudTest extends TestCase
{
    use RefreshDatabase;

    private Service $coupe;
    private Service $massage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->coupe = Service::factory()->create(['nom' => 'Coupe test']);
        $this->massage = Service::factory()->create(['nom' => 'Massage test']);
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'nom' => 'Fall',
            'prenom' => 'Awa',
            'telephone' => '77 123 45 67',
            'fonction' => 'Coiffeuse',
            'specialites' => 'Tresses',
            'is_active' => 1,
            'services' => [$this->coupe->id, $this->massage->id],
            'commission_type' => 'pourcentage',
            'commission_valeur' => 10,
        ], $override);
    }

    /** Employé identique à data(), pour tester les modifications. */
    private function existingEmploye(): Employe
    {
        $employe = Employe::factory()->create([
            'nom' => 'Fall',
            'prenom' => 'Awa',
            'telephone' => '77 123 45 67',
            'fonction' => 'Coiffeuse',
            'specialites' => 'Tresses',
            'commission_type' => TypeCommission::Pourcentage,
            'commission_valeur' => 10,
        ]);
        $employe->services()->sync([$this->coupe->id, $this->massage->id]);

        return $employe;
    }

    private function admin(): User
    {
        return User::factory()->administratrice()->create();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('employes.index'))->assertRedirect(route('login'));
    }

    public function test_administratrice_et_receptionniste_consultent_l_equipe_le_professionnel_non(): void
    {
        $employe = Employe::factory()->create(['nom' => 'Ndiaye', 'prenom' => 'Fatou']);

        foreach (['administratrice', 'receptionniste'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('employes.index'))->assertOk()->assertSee('Fatou Ndiaye');
            $this->get(route('employes.show', $employe))->assertOk()->assertSee('Fatou Ndiaye');
        }

        $this->actingAs(User::factory()->professionnel()->create());

        $this->get(route('employes.index'))->assertForbidden();
        $this->get(route('employes.show', $employe))->assertForbidden();
    }

    public function test_les_roles_sans_droit_ne_peuvent_rien_modifier(): void
    {
        $employe = $this->existingEmploye();

        foreach (['receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('employes.create'))->assertForbidden();
            $this->post(route('employes.store'), $this->data(['nom' => 'Autre']))->assertForbidden();
            $this->get(route('employes.edit', $employe))->assertForbidden();
            $this->put(route('employes.update', $employe), $this->data(['fonction' => 'Autre']))->assertForbidden();
            $this->patch(route('employes.toggle', $employe))->assertForbidden();
            $this->get(route('employes.disponibilites.edit', $employe))->assertForbidden();
        }

        $this->assertSame(1, Employe::count());
        $this->assertSame('Coiffeuse', $employe->fresh()->fonction);
        $this->assertTrue($employe->fresh()->is_active);
    }

    public function test_l_administratrice_cree_un_employe(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('employes.store'), $this->data());

        $employe = Employe::firstWhere('nom', 'Fall');

        $this->assertNotNull($employe);
        $this->assertSame('Awa Fall', $employe->nomComplet());
        $this->assertSame(2, $employe->services()->count());
        $this->assertSame(TypeCommission::Pourcentage, $employe->commission_type);
        $this->assertSame(10, $employe->commission_valeur);
        $this->assertDatabaseHas('historique_actions', [
            'user_id' => $admin->id,
            'action' => 'employe.cree',
            'sujet_id' => $employe->id,
        ]);
    }

    public function test_les_donnees_invalides_sont_refusees(): void
    {
        $this->actingAs($this->admin());

        $cas = [
            [['nom' => ''], 'nom'],
            [['prenom' => ''], 'prenom'],
            [['fonction' => ''], 'fonction'],
            [['telephone' => 'abc'], 'telephone'],
            [['commission_type' => 'autre'], 'commission_type'],
            [['commission_valeur' => -1], 'commission_valeur'],
            [['commission_type' => 'pourcentage', 'commission_valeur' => 101], 'commission_valeur'],
            [['commission_type' => 'fixe', 'commission_valeur' => ''], 'commission_valeur'],
            [['services' => [9999]], 'services.0'],
            [['user_id' => 9999], 'user_id'],
        ];

        foreach ($cas as [$override, $champ]) {
            $this->post(route('employes.store'), $this->data($override))->assertSessionHasErrors($champ);
        }

        $this->assertSame(0, Employe::count());
    }

    public function test_un_compte_ne_peut_etre_lie_qu_a_un_seul_employe(): void
    {
        $user = User::factory()->professionnel()->create();
        Employe::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin());

        $this->post(route('employes.store'), $this->data(['user_id' => $user->id]))
            ->assertSessionHasErrors('user_id');

        $libre = User::factory()->create();

        $this->post(route('employes.store'), $this->data(['user_id' => $libre->id]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Employe::where('user_id', $libre->id)->exists());
    }

    public function test_la_commission_est_invisible_sans_la_permission(): void
    {
        $employe = Employe::factory()
            ->avecCommission(TypeCommission::Pourcentage, 15)
            ->create();

        $this->actingAs($this->admin());
        $this->get(route('employes.index'))->assertSee('Commission')->assertSee('15 %');
        $this->get(route('employes.show', $employe))->assertSee('Commission')->assertSee('15 %');

        $this->actingAs(User::factory()->receptionniste()->create());
        $this->get(route('employes.index'))->assertOk()->assertDontSee('Commission')->assertDontSee('15 %');
        $this->get(route('employes.show', $employe))->assertOk()->assertDontSee('Commission')->assertDontSee('15 %');
    }

    public function test_staff_manage_sans_view_earnings_ne_peut_pas_modifier_la_commission(): void
    {
        $employe = $this->existingEmploye();

        $user = User::factory()->receptionniste()->create();
        $user->grantPermission('staff.manage');
        $this->actingAs($user);

        $this->get(route('employes.edit', $employe))
            ->assertOk()
            ->assertDontSee('Type de commission');

        $this->put(route('employes.update', $employe), $this->data([
            'fonction' => 'Styliste',
            'commission_type' => 'fixe',
            'commission_valeur' => 999,
        ]))->assertRedirect(route('employes.show', $employe));

        $employe = $employe->fresh();

        $this->assertSame('Styliste', $employe->fonction);
        $this->assertSame(TypeCommission::Pourcentage, $employe->commission_type);
        $this->assertSame(10, $employe->commission_valeur);
    }

    public function test_une_modification_est_journalisee(): void
    {
        $employe = $this->existingEmploye();

        $this->actingAs($this->admin())
            ->put(route('employes.update', $employe), $this->data(['fonction' => 'Styliste']))
            ->assertRedirect(route('employes.show', $employe));

        $log = HistoriqueAction::where('action', 'employe.modifie')->first();

        $this->assertNotNull($log);
        $this->assertSame('Coiffeuse', $log->anciennes_valeurs['fonction']);
        $this->assertSame('Styliste', $log->nouvelles_valeurs['fonction']);
    }

    public function test_une_modification_sans_changement_n_ecrit_rien_dans_le_journal(): void
    {
        $employe = $this->existingEmploye();

        $this->actingAs($this->admin())
            ->put(route('employes.update', $employe), $this->data())
            ->assertRedirect(route('employes.show', $employe));

        $this->assertSame(0, HistoriqueAction::where('action', 'employe.modifie')->count());
    }

    public function test_on_peut_desactiver_puis_reactiver_un_employe(): void
    {
        $employe = Employe::factory()->create();
        $this->actingAs($this->admin());

        $this->patch(route('employes.toggle', $employe))->assertRedirect();
        $this->assertFalse($employe->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'employe.desactive', 'sujet_id' => $employe->id]);

        $this->patch(route('employes.toggle', $employe))->assertRedirect();
        $this->assertTrue($employe->fresh()->is_active);
        $this->assertDatabaseHas('historique_actions', ['action' => 'employe.active', 'sujet_id' => $employe->id]);
    }

    public function test_il_n_existe_aucune_route_de_suppression(): void
    {
        $this->assertFalse(Route::has('employes.destroy'));
    }

    public function test_les_filtres_de_la_liste_fonctionnent(): void
    {
        Employe::factory()->create(['nom' => 'Alpha', 'prenom' => 'Aminata']);
        Employe::factory()->inactif()->create(['nom' => 'Beta', 'prenom' => 'Binta']);

        $this->actingAs($this->admin());

        $this->get(route('employes.index', ['q' => 'Binta']))
            ->assertSee('Binta Beta')
            ->assertDontSee('Aminata');

        $this->get(route('employes.index', ['statut' => 'actifs']))
            ->assertSee('Aminata')
            ->assertDontSee('Binta');

        $this->get(route('employes.index', ['statut' => 'inactifs']))
            ->assertSee('Binta')
            ->assertDontSee('Aminata');
    }

    public function test_les_libelles_du_modele(): void
    {
        $this->assertSame('Awa Fall', Employe::factory()->make(['prenom' => 'Awa', 'nom' => 'Fall'])->nomComplet());
        $this->assertSame('Aucune', Employe::factory()->make(['commission_type' => null])->commissionLibelle());
        $this->assertSame('10 %', Employe::factory()
            ->make(['commission_type' => TypeCommission::Pourcentage, 'commission_valeur' => 10])
            ->commissionLibelle());
        $this->assertSame(Fcfa::format(500).' par prestation', Employe::factory()
            ->make(['commission_type' => TypeCommission::Fixe, 'commission_valeur' => 500])
            ->commissionLibelle());
    }

    public function test_un_compte_ne_peut_etre_lie_deux_fois_en_base(): void
    {
        $user = User::factory()->create();
        Employe::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        Employe::factory()->create(['user_id' => $user->id]);
    }
}