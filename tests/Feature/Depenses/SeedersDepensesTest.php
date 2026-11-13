<?php

namespace Tests\Feature\Depenses;

use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\User;
use Database\Seeders\CategoriesDepensesSeeder;
use Database\Seeders\DemoDepensesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersDepensesTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_les_sept_categories_dans_l_ordre(): void
    {
        $this->seed(CategoriesDepensesSeeder::class);

        $noms = CategorieDepense::orderBy('ordre')->pluck('nom')->all();

        $this->assertCount(7, $noms);
        $this->assertSame('Achat de produits', $noms[0]);
        $this->assertSame('Autres dépenses', $noms[6]);
    }

    public function test_le_seeder_ne_recree_rien_si_les_categories_existent(): void
    {
        $this->seed(CategoriesDepensesSeeder::class);
        CategorieDepense::where('nom', 'Loyer')->update(['nom' => 'Loyer du salon']);

        $this->seed(CategoriesDepensesSeeder::class);

        $this->assertSame(7, CategorieDepense::count());
        $this->assertDatabaseHas('categories_depenses', ['nom' => 'Loyer du salon']);
        $this->assertDatabaseMissing('categories_depenses', ['nom' => 'Loyer']);
    }

    public function test_les_donnees_de_demonstration_sont_creees_et_repetables(): void
    {
        User::factory()->administratrice()->create();

        $this->seed(DemoDepensesSeeder::class);
        $this->assertSame(4, Depense::count());

        $this->seed(DemoDepensesSeeder::class);
        $this->assertSame(4, Depense::count());
    }

    public function test_les_donnees_de_demonstration_survivent_a_une_categorie_renommee(): void
    {
        User::factory()->administratrice()->create();
        $this->seed(CategoriesDepensesSeeder::class);
        CategorieDepense::where('nom', 'Eau')->update(['nom' => 'Eau courante']);

        $this->seed(DemoDepensesSeeder::class);

        $this->assertSame(4, Depense::count());
    }

    public function test_le_seeder_de_demonstration_ne_fait_rien_sans_administratrice(): void
    {
        $this->seed(DemoDepensesSeeder::class);

        $this->assertSame(0, Depense::count());
    }
}