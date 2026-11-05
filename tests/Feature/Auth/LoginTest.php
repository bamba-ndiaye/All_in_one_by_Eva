<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_la_page_de_connexion_affiche_le_nom_du_salon(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('All in One')
            ->assertSee('by Eva');
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_connexion_reussie(): void
    {
        $user = User::factory()->receptionniste()->create(['email' => 'eva@example.test']);

        $this->post(route('login.store'), [
            'email' => 'EVA@example.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('historique_actions', [
            'user_id' => $user->id,
            'action' => 'auth.connexion',
        ]);
    }

    public function test_un_mauvais_mot_de_passe_est_refuse(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = User::factory()->inactif()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_un_email_inconnu_donne_le_meme_message(): void
    {
        $this->post(route('login.store'), [
            'email' => 'inconnu@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_les_champs_sont_obligatoires(): void
    {
        $this->post(route('login.store'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_apres_cinq_echecs_la_connexion_est_bloquee(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'mauvais',
            ]);
        }

        // Même avec le bon mot de passe, le blocage s'applique.
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNotSame(trans('auth.failed'), session('errors')->first('email'));
    }

    public function test_la_deconnexion_est_journalisee(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('historique_actions', [
            'user_id' => $user->id,
            'action' => 'auth.deconnexion',
        ]);
    }

    public function test_un_visiteur_ne_peut_pas_appeler_la_deconnexion(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_un_utilisateur_connecte_est_renvoye_de_la_page_de_connexion(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect('/');
    }

    public function test_les_textes_francais_existent(): void
    {
        $this->assertSame(
            'Le champ nom est obligatoire.',
            trans('validation.required', ['attribute' => 'nom'], 'fr'),
        );
        $this->assertNotSame('auth.failed', trans('auth.failed', [], 'fr'));
    }
}