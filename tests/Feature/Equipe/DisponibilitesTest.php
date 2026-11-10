<?php

namespace Tests\Feature\Equipe;

use App\Models\Employe;
use App\Models\HistoriqueAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibilitesTest extends TestCase
{
    use RefreshDatabase;

    private Employe $employe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->employe = Employe::factory()->create(['nom' => 'Fall', 'prenom' => 'Awa']);
    }

    private function plage(int $jour, string $debut, string $fin): array
    {
        return ['jour' => $jour, 'debut' => $debut, 'fin' => $fin];
    }

    private function save(array $plages)
    {
        return $this->put(route('employes.disponibilites.update', $this->employe), ['plages' => $plages]);
    }

    private function admin(): User
    {
        return User::factory()->administratrice()->create();
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('employes.disponibilites.edit', $this->employe))->assertRedirect(route('login'));
    }

    public function test_les_roles_sans_droit_de_gestion_sont_refuses(): void
    {
        foreach (['receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('employes.disponibilites.edit', $this->employe))->assertForbidden();
            $this->save([$this->plage(1, '09:00', '12:00')])->assertForbidden();
        }

        $this->assertSame(0, $this->employe->disponibilites()->count());
    }

    public function test_l_administratrice_ouvre_la_page_des_horaires(): void
    {
        $this->actingAs($this->admin())
            ->get(route('employes.disponibilites.edit', $this->employe))
            ->assertOk()
            ->assertSee('Awa Fall');
    }

    public function test_des_plages_valides_sont_enregistrees_et_journalisees(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        $this->save([
            $this->plage(1, '09:00', '13:00'),
            $this->plage(1, '14:00', '18:00'),
            $this->plage(6, '09:00', '14:00'),
        ])->assertRedirect(route('employes.show', $this->employe));

        $this->assertSame(3, $this->employe->disponibilites()->count());
        $this->assertDatabaseHas('historique_actions', [
            'user_id' => $admin->id,
            'action' => 'employe.horaires',
            'sujet_id' => $this->employe->id,
        ]);
    }

    public function test_une_fin_avant_le_debut_est_refusee(): void
    {
        $this->actingAs($this->admin());

        $this->save([$this->plage(1, '12:00', '09:00')])->assertSessionHasErrors('plages.0.fin');
        $this->save([$this->plage(1, '09:00', '09:00')])->assertSessionHasErrors('plages.0.fin');

        $this->assertSame(0, $this->employe->disponibilites()->count());
    }

    public function test_deux_plages_qui_se_chevauchent_sont_refusees(): void
    {
        $this->actingAs($this->admin());

        $this->save([
            $this->plage(2, '09:00', '12:00'),
            $this->plage(2, '11:00', '15:00'),
        ])->assertSessionHasErrors('plages');

        $this->assertSame(0, $this->employe->disponibilites()->count());
    }

    public function test_deux_plages_qui_se_touchent_sont_acceptees(): void
    {
        $this->actingAs($this->admin());

        $this->save([
            $this->plage(2, '09:00', '12:00'),
            $this->plage(2, '12:00', '15:00'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->employe->disponibilites()->count());
    }

    public function test_un_jour_ou_une_heure_invalide_est_refuse(): void
    {
        $this->actingAs($this->admin());

        $this->save([$this->plage(8, '09:00', '12:00')])->assertSessionHasErrors('plages.0.jour');
        $this->save([$this->plage(0, '09:00', '12:00')])->assertSessionHasErrors('plages.0.jour');
        $this->save([$this->plage(1, '9h', '12:00')])->assertSessionHasErrors('plages.0.debut');
        $this->save([$this->plage(1, '09:00', '25:00')])->assertSessionHasErrors('plages.0.fin');
    }

    public function test_l_enregistrement_remplace_les_plages_existantes(): void
    {
        $this->employe->disponibilites()->create(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '12:00']);
        $this->employe->disponibilites()->create(['jour_semaine' => 2, 'heure_debut' => '09:00', 'heure_fin' => '12:00']);

        $this->actingAs($this->admin());
        $this->save([$this->plage(5, '10:00', '16:00')]);

        $plages = $this->employe->disponibilites()->get();

        $this->assertCount(1, $plages);
        $this->assertSame(5, $plages->first()->jour_semaine->value);
        $this->assertSame('10:00', $plages->first()->debut());
        $this->assertSame('16:00', $plages->first()->fin());
    }

    public function test_enregistrer_sans_plage_efface_les_horaires(): void
    {
        $this->employe->disponibilites()->create(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '12:00']);

        $this->actingAs($this->admin());
        $this->save([])->assertRedirect(route('employes.show', $this->employe));

        $this->assertSame(0, $this->employe->disponibilites()->count());
        $this->assertSame(1, HistoriqueAction::where('action', 'employe.horaires')->count());
    }

    public function test_un_enregistrement_sans_changement_n_ecrit_rien_dans_le_journal(): void
    {
        $this->employe->disponibilites()->create(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '12:00']);

        $this->actingAs($this->admin());
        $this->save([$this->plage(1, '09:00', '12:00')])->assertRedirect(route('employes.show', $this->employe));

        $this->assertSame(0, HistoriqueAction::where('action', 'employe.horaires')->count());
        $this->assertSame(1, $this->employe->disponibilites()->count());
    }

    public function test_la_fiche_affiche_les_horaires_et_les_jours_de_repos(): void
    {
        $this->employe->disponibilites()->create(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '12:00']);

        $this->actingAs(User::factory()->receptionniste()->create())
            ->get(route('employes.show', $this->employe))
            ->assertOk()
            ->assertSee('09:00')
            ->assertSee('12:00')
            ->assertSee('Repos');
    }
}