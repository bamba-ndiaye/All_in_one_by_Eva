<?php

namespace Tests\Feature\Catalogue;

use App\Enums\ZoneSalon;
use App\Models\CategorieService;
use App\Models\Forfait;
use App\Models\Service;
use App\Support\Fcfa;
use Database\Seeders\CategoriesServicesSeeder;
use Database\Seeders\DemoServicesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeder_cree_les_onze_categories_dans_l_ordre(): void
    {
        $this->seed(CategoriesServicesSeeder::class);

        $noms = CategorieService::orderBy('ordre')->pluck('nom')->all();

        $this->assertCount(11, $noms);
        $this->assertSame('Coiffure hommes', $noms[0]);
        $this->assertSame('Forfaits combinés', $noms[10]);
    }

    public function test_le_seeder_ne_recree_rien_si_les_categories_existent(): void
    {
        $this->seed(CategoriesServicesSeeder::class);
        CategorieService::where('nom', 'Tresses')->update(['nom' => 'Tresses et nattes']);

        $this->seed(CategoriesServicesSeeder::class);

        $this->assertSame(11, CategorieService::count());
        $this->assertDatabaseHas('categories_services', ['nom' => 'Tresses et nattes']);
        $this->assertDatabaseMissing('categories_services', ['nom' => 'Tresses']);
    }

    public function test_un_service_a_un_prix_entier_et_une_zone(): void
    {
        $service = Service::factory()->create(['prix' => 5000, 'zone' => ZoneSalon::Femmes])->fresh();

        $this->assertSame(5000, $service->prix);
        $this->assertSame(ZoneSalon::Femmes, $service->zone);
        $this->assertTrue($service->is_active);
    }

    public function test_deux_services_de_meme_nom_dans_une_categorie_sont_refuses(): void
    {
        $categorie = CategorieService::factory()->create();
        Service::factory()->create(['categorie_service_id' => $categorie->id, 'nom' => 'Coupe']);

        $this->expectException(QueryException::class);

        Service::factory()->create(['categorie_service_id' => $categorie->id, 'nom' => 'Coupe']);
    }

    public function test_le_meme_nom_est_possible_dans_deux_categories(): void
    {
        Service::factory()->create(['nom' => 'Soin']);
        Service::factory()->create(['nom' => 'Soin']);

        $this->assertSame(2, Service::where('nom', 'Soin')->count());
    }

    public function test_le_scope_actifs_ignore_les_services_desactives(): void
    {
        Service::factory()->create();
        Service::factory()->inactif()->create();

        $this->assertSame(1, Service::actifs()->count());
    }

    public function test_un_forfait_calcule_duree_prix_separe_et_economie(): void
    {
        $a = Service::factory()->create(['prix' => 5000, 'duree_minutes' => 45]);
        $b = Service::factory()->create(['prix' => 6000, 'duree_minutes' => 60]);
        $forfait = Forfait::factory()->create(['prix' => 10000]);
        $forfait->services()->sync([$a->id, $b->id]);

        $forfait = $forfait->fresh();

        $this->assertSame(105, $forfait->dureeTotale());
        $this->assertSame(11000, $forfait->prixSepare());
        $this->assertSame(1000, $forfait->economie());
        $this->assertSame('1 h 45', $forfait->dureeFormatee());
    }

    public function test_la_zone_d_un_forfait_est_femmes_des_qu_un_service_l_est(): void
    {
        $mixte = Service::factory()->create();
        $femmes = Service::factory()->femmes()->create();

        $forfaitMixte = Forfait::factory()->create();
        $forfaitMixte->services()->sync([$mixte->id]);

        $forfaitFemmes = Forfait::factory()->create();
        $forfaitFemmes->services()->sync([$mixte->id, $femmes->id]);

        $this->assertSame(ZoneSalon::Mixte, $forfaitMixte->fresh()->zone());
        $this->assertSame(ZoneSalon::Femmes, $forfaitFemmes->fresh()->zone());
    }

    public function test_les_durees_sont_formatees_lisiblement(): void
    {
        $this->assertSame('45 min', Service::formatDuree(45));
        $this->assertSame('2 h', Service::formatDuree(120));
        $this->assertSame('1 h 30', Service::formatDuree(90));
        $this->assertSame('1 h 05', Service::formatDuree(65));
    }

    public function test_le_format_fcfa_utilise_des_espaces_insecables(): void
    {
        $this->assertSame("5\u{00A0}000\u{00A0}FCFA", Fcfa::format(5000));
        $this->assertSame("0\u{00A0}FCFA", Fcfa::format(0));
        $this->assertSame("1\u{00A0}250\u{00A0}000\u{00A0}FCFA", Fcfa::format(1250000));
    }

    public function test_les_donnees_de_demonstration_sont_coherentes_et_repetables(): void
    {
        $this->seed(DemoServicesSeeder::class);
        $this->seed(DemoServicesSeeder::class);

        $this->assertSame(11, CategorieService::count());
        $this->assertSame(8, Service::count());
        $this->assertSame(2, Forfait::count());

        foreach (Forfait::with('services')->get() as $forfait) {
            $this->assertGreaterThanOrEqual(2, $forfait->services->count());
        }
    }
}