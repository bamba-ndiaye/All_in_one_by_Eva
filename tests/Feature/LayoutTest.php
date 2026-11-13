<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->administratrice()->create());
    }

    public function test_la_page_d_accueil_affiche_le_nom_du_salon(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('All in One')
            ->assertSee('by Eva');
    }

    public function test_toutes_les_entrees_du_menu_menent_a_une_page(): void
    {
        foreach (config('navigation.items') as $item) {
            $this->assertTrue(Route::has($item['route']), "Route manquante : {$item['route']}");
            $this->get(route($item['route']))->assertOk();
        }

        $this->get('/')->assertDontSee('Bientôt');
    }
}