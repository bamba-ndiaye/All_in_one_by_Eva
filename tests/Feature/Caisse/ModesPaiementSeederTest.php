<?php

namespace Tests\Feature\Caisse;

use App\Models\ModePaiement;
use Database\Seeders\ModesPaiementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModesPaiementSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_les_cinq_modes_dans_l_ordre(): void
    {
        $this->seed(ModesPaiementSeeder::class);

        $this->assertSame(
            ['Espèces', 'Wave', 'Orange Money', 'Carte bancaire', 'Autre'],
            ModePaiement::orderBy('ordre')->pluck('nom')->all(),
        );
        $this->assertSame(5, ModePaiement::actifs()->count());
    }

    public function test_le_seeder_ne_recree_rien_si_les_modes_existent(): void
    {
        $this->seed(ModesPaiementSeeder::class);
        ModePaiement::where('nom', 'Wave')->update(['nom' => 'Wave Money']);

        $this->seed(ModesPaiementSeeder::class);

        $this->assertSame(5, ModePaiement::count());
        $this->assertDatabaseHas('modes_paiement', ['nom' => 'Wave Money']);
        $this->assertDatabaseMissing('modes_paiement', ['nom' => 'Wave']);
    }
}