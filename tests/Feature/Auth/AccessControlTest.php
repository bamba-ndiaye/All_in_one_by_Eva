<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Route::middleware(['auth', 'active', 'role:administratrice'])
            ->get('/_test/admin', fn () => 'ok');

        Route::middleware(['auth', 'active', 'can:clients.export'])
            ->get('/_test/export', fn () => 'ok');
    }

    public function test_un_visiteur_est_redirige_sur_toute_page_protegee(): void
    {
        $this->get('/_test/admin')->assertRedirect(route('login'));
        $this->get('/_test/export')->assertRedirect(route('login'));
    }

    public function test_l_administratrice_accede_a_la_page_reservee(): void
    {
        $this->actingAs(User::factory()->administratrice()->create())
            ->get('/_test/admin')
            ->assertOk();
    }

    public function test_la_receptionniste_recoit_un_403_en_tapant_l_url(): void
    {
        $this->actingAs(User::factory()->receptionniste()->create())
            ->get('/_test/admin')
            ->assertForbidden();
    }

    public function test_une_permission_manquante_donne_403_puis_acces_apres_octroi(): void
    {
        $user = User::factory()->receptionniste()->create();
        $this->actingAs($user);

        $this->get('/_test/export')->assertForbidden();

        $user->grantPermission('clients.export');

        $this->get('/_test/export')->assertOk();
    }

    public function test_un_compte_desactive_en_cours_de_session_est_deconnecte(): void
    {
        $this->actingAs(User::factory()->inactif()->create())
            ->get('/')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_le_tableau_de_bord_est_accessible_a_chaque_role(): void
    {
        foreach (['administratrice', 'receptionniste', 'professionnel'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get('/')
                ->assertOk()
                ->assertSee('Bonjour');
        }
    }

     public function test_le_menu_masque_les_modules_sans_permission(): void
    {
        $html = $this->actingAs(User::factory()->professionnel()->create())
            ->get('/')
            ->assertOk()
            ->getContent();

        preg_match('/<nav.*?<\/nav>/s', $html, $matches);
        $menu = $matches[0] ?? '';

        $this->assertStringContainsString('Tableau de bord', $menu);
        $this->assertStringContainsString('Prestations', $menu);
        $this->assertStringNotContainsString('Clients', $menu);
        $this->assertStringNotContainsString('Caisse', $menu);
        $this->assertStringNotContainsString('Paramètres', $menu);
    }
}