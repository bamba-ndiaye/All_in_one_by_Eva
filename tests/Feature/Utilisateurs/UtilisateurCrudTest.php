<?php

namespace Tests\Feature\Utilisateurs;

use App\Enums\RoleUtilisateur;
use App\Models\HistoriqueAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UtilisateurCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->administratrice()->create();
    }

    /** Réceptionniste à qui l'on a confié la gestion des comptes. */
    private function gestionnaire(): User
    {
        $user = User::factory()->receptionniste()->create();
        $user->grantPermission('users.manage');

        return $user;
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'name' => 'Awa Fall',
            'email' => 'Awa.Fall@Example.com',
            'role' => 'receptionniste',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'is_active' => 1,
            'permissions' => [],
        ], $override);
    }

    // --- Accès ---------------------------------------------------------------------

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('utilisateurs.index'))->assertRedirect(route('login'));
    }

    public function test_sans_le_droit_users_manage_l_acces_est_refuse(): void
    {
        foreach (['receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->get(route('utilisateurs.index'))->assertForbidden();
            $this->get(route('utilisateurs.create'))->assertForbidden();
            $this->post(route('utilisateurs.store'), $this->data())->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'awa.fall@example.com']);
    }

    public function test_l_administratrice_voit_la_liste_et_le_menu(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Fatou Ndiaye']);

        $this->actingAs($admin)
            ->get(route('utilisateurs.index'))
            ->assertOk()
            ->assertSee('Fatou Ndiaye')
            ->assertSee(route('utilisateurs.index'), false);
    }

    public function test_les_filtres_inattendus_ne_cassent_pas_la_liste(): void
    {
        $this->actingAs($this->admin())
            ->get(route('utilisateurs.index', ['q' => ['x'], 'role' => 'pirate', 'statut' => ['y']]))
            ->assertOk();
    }

    public function test_recherche_par_role_et_statut(): void
    {
        $admin = $this->admin();
        User::factory()->professionnel()->create(['name' => 'Coiffeuse Active']);
        User::factory()->professionnel()->inactif()->create(['name' => 'Coiffeuse Partie']);
        User::factory()->receptionniste()->create(['name' => 'Accueil Salon']);

        $this->actingAs($admin)
            ->get(route('utilisateurs.index', ['role' => 'professionnel', 'statut' => 'actifs']))
            ->assertOk()
            ->assertSee('Coiffeuse Active')
            ->assertDontSee('Coiffeuse Partie')
            ->assertDontSee('Accueil Salon');
    }

    // --- Création ------------------------------------------------------------------

    public function test_l_administratrice_cree_un_compte_qui_peut_se_connecter(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('utilisateurs.store'), $this->data(['permissions' => ['clients.export']]))
            ->assertRedirect();

        $user = User::where('email', 'awa.fall@example.com')->firstOrFail();

        $this->assertSame(RoleUtilisateur::Receptionniste, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('motdepasse-solide', $user->password));
        $this->assertTrue($user->hasPermission('clients.export'));
        $this->assertSame(['clients.export'], $user->extraPermissions()->pluck('permission')->all());

        auth()->logout();

        $this->post(route('login.store'), ['email' => 'awa.fall@example.com', 'password' => 'motdepasse-solide'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_le_journal_ne_contient_jamais_le_mot_de_passe(): void
    {
        $this->actingAs($this->admin())->post(route('utilisateurs.store'), $this->data());

        $log = HistoriqueAction::where('action', 'utilisateur.cree')->firstOrFail();

        $this->assertStringNotContainsString('motdepasse-solide', json_encode($log->toArray()));
        $this->assertArrayNotHasKey('password', $log->nouvelles_valeurs);
    }

    public function test_les_droits_deja_inclus_dans_le_role_ne_sont_pas_dupliques(): void
    {
        $this->actingAs($this->admin())
            ->post(route('utilisateurs.store'), $this->data(['permissions' => ['clients.view', 'reports.view']]));

        $user = User::where('email', 'awa.fall@example.com')->firstOrFail();

        $this->assertSame(['reports.view'], $user->extraPermissions()->pluck('permission')->all());
    }

    public function test_validation_email_unique_mot_de_passe_et_permission_inconnue(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'awa.fall@example.com']);

        $this->actingAs($admin)
            ->post(route('utilisateurs.store'), $this->data([
                'password' => 'court',
                'password_confirmation' => 'different',
                'permissions' => ['inventee.totalement'],
            ]))
            ->assertSessionHasErrors(['email', 'password', 'permissions.0']);
    }

    public function test_un_gestionnaire_non_admin_ne_peut_pas_creer_d_administratrice(): void
    {
        $this->actingAs($this->gestionnaire())
            ->post(route('utilisateurs.store'), $this->data(['role' => 'administratrice']))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'awa.fall@example.com']);
    }

    public function test_un_gestionnaire_non_admin_n_accorde_pas_un_droit_qu_il_n_a_pas(): void
    {
        $this->actingAs($this->gestionnaire())
            ->post(route('utilisateurs.store'), $this->data(['role' => 'professionnel', 'permissions' => ['finance.view_revenue']]))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'awa.fall@example.com']);
    }

    public function test_un_gestionnaire_non_admin_accorde_un_droit_qu_il_possede(): void
    {
        $this->actingAs($this->gestionnaire())
            ->post(route('utilisateurs.store'), $this->data(['role' => 'professionnel', 'permissions' => ['clients.view']]))
            ->assertRedirect();

        $user = User::where('email', 'awa.fall@example.com')->firstOrFail();
        $this->assertTrue($user->hasPermission('clients.view'));
    }

    // --- Modification --------------------------------------------------------------

    public function test_modifier_sans_mot_de_passe_garde_l_ancien(): void
    {
        $admin = $this->admin();
        $user = User::factory()->receptionniste()->create(['password' => 'ancien-mot-de-passe']);

        $this->actingAs($admin)
            ->put(route('utilisateurs.update', $user), $this->data([
                'name' => 'Nouveau Nom',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('utilisateurs.show', $user));

        $user->refresh();
        $this->assertSame('Nouveau Nom', $user->name);
        $this->assertTrue(Hash::check('ancien-mot-de-passe', $user->password));
    }

    public function test_changer_le_role_et_retirer_un_droit_est_journalise(): void
    {
        $admin = $this->admin();
        $user = User::factory()->receptionniste()->create();
        $user->grantPermission('reports.view');

        $this->actingAs($admin)
            ->put(route('utilisateurs.update', $user), $this->data([
                'email' => $user->email,
                'role' => 'professionnel',
                'password' => '',
                'password_confirmation' => '',
                'permissions' => [],
            ]))
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(RoleUtilisateur::Professionnel, $user->role);
        $this->assertFalse($user->hasPermission('reports.view'));

        $log = HistoriqueAction::where('action', 'utilisateur.modifie')->latest('id')->firstOrFail();
        $this->assertSame('receptionniste', $log->anciennes_valeurs['role']);
        $this->assertSame(['reports.view'], $log->anciennes_valeurs['permissions']);
    }

    public function test_on_ne_peut_pas_changer_son_propre_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('utilisateurs.update', $admin), $this->data([
                'email' => $admin->email,
                'role' => 'receptionniste',
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertSame(RoleUtilisateur::Administratrice, $admin->refresh()->role);
    }

    public function test_un_gestionnaire_non_admin_ne_touche_pas_au_compte_d_une_administratrice(): void
    {
        $admin = $this->admin();

        $this->actingAs($this->gestionnaire());

        $this->get(route('utilisateurs.edit', $admin))->assertForbidden();
        $this->put(route('utilisateurs.update', $admin), $this->data([
            'email' => $admin->email, 'role' => 'administratrice', 'password' => 'pirate-pirate', 'password_confirmation' => 'pirate-pirate',
        ]))->assertForbidden();
        $this->patch(route('utilisateurs.toggle', $admin))->assertForbidden();

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_un_gestionnaire_ne_peut_pas_promouvoir_un_compte_administratrice(): void
    {
        $user = User::factory()->receptionniste()->create();

        $this->actingAs($this->gestionnaire())
            ->put(route('utilisateurs.update', $user), $this->data([
                'email' => $user->email, 'role' => 'administratrice', 'password' => '', 'password_confirmation' => '',
            ]))
            ->assertForbidden();

        $this->assertSame(RoleUtilisateur::Receptionniste, $user->refresh()->role);
    }

    public function test_un_droit_que_le_gestionnaire_n_a_pas_est_conserve_tel_quel(): void
    {
        $user = User::factory()->professionnel()->create();
        $user->grantPermission('finance.view_revenue');

        // Le formulaire n'envoie pas ce droit (case désactivée) : il ne doit pas disparaître.
        $this->actingAs($this->gestionnaire())
            ->put(route('utilisateurs.update', $user), $this->data([
                'email' => $user->email, 'role' => 'professionnel', 'password' => '', 'password_confirmation' => '',
                'permissions' => ['clients.view'],
            ]))
            ->assertRedirect();

        $this->assertSame(
            ['clients.view', 'finance.view_revenue'],
            $user->extraPermissions()->orderBy('permission')->pluck('permission')->all(),
        );
    }

    public function test_un_nouveau_mot_de_passe_ferme_les_sessions_de_la_personne(): void
    {
        config(['session.driver' => 'database']);

        $admin = $this->admin();
        $user = User::factory()->receptionniste()->create();
        DB::table('sessions')->insert([
            'id' => 'session-de-test', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->put(route('utilisateurs.update', $user), $this->data([
                'email' => $user->email, 'password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe',
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->refresh()->password));
    }

    // --- Activation ----------------------------------------------------------------

    public function test_desactiver_un_compte_empeche_la_connexion_et_ferme_ses_sessions(): void
    {
        config(['session.driver' => 'database']);

        $admin = $this->admin();
        $user = User::factory()->receptionniste()->create(['password' => 'motdepasse-solide']);
        DB::table('sessions')->insert([
            'id' => 'session-de-test', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->patch(route('utilisateurs.toggle', $user))
            ->assertRedirect();

        $this->assertFalse($user->refresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('historique_actions', ['action' => 'utilisateur.desactive', 'sujet_id' => $user->id]);

        auth()->logout();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'motdepasse-solide'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)->patch(route('utilisateurs.toggle', $user));
        $this->assertTrue($user->refresh()->is_active);
    }

    public function test_on_ne_peut_pas_desactiver_son_propre_compte(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('utilisateurs.toggle', $admin))
            ->assertSessionHasErrors('compte');

        $this->assertTrue($admin->refresh()->is_active);
    }

    // --- Pages ---------------------------------------------------------------------

    public function test_les_pages_s_affichent(): void
    {
        $admin = $this->admin();
        $user = User::factory()->receptionniste()->create(['name' => 'Mariama Sow']);
        $user->grantPermission('reports.view');

        $this->actingAs($admin);

        $this->get(route('utilisateurs.create'))->assertOk()->assertSee('Nouveau compte');
        $this->get(route('utilisateurs.show', $user))->assertOk()->assertSee('Mariama Sow')->assertSee('Consulter les rapports');
        $this->get(route('utilisateurs.edit', $user))->assertOk()->assertSee('Mariama Sow');
        $this->get(route('utilisateurs.edit', $admin))->assertOk()->assertSee('Vous ne pouvez pas changer votre propre rôle.');
    }

    public function test_un_identifiant_inexistant_renvoie_404(): void
    {
        $this->actingAs($this->admin())->get(route('utilisateurs.show', 999999))->assertNotFound();
    }
}
