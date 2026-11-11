<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiltresInattendusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->administratrice()->create());
    }

    public function test_les_prestations_ignorent_les_filtres_mal_formes(): void
    {
        $this->get(route('services.index', ['q' => ['a'], 'zone' => ['b'], 'statut' => ['c'], 'categorie' => ['d']]))
            ->assertOk();
    }

    public function test_l_equipe_ignore_les_filtres_mal_formes(): void
    {
        $this->get(route('employes.index', ['q' => ['a'], 'statut' => ['c']]))->assertOk();
    }
}