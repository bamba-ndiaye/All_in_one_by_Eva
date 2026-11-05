<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_les_modules_non_crees_sont_marques_bientot(): void
    {
        $this->get('/')->assertSee('Bientôt');
    }
}