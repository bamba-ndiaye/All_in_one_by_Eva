<?php

namespace Database\Seeders;

use App\Enums\ZoneSalon;
use App\Models\CategorieService;
use App\Models\Forfait;
use App\Models\Service;
use Illuminate\Database\Seeder;

class DemoServicesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategoriesServicesSeeder::class);

        // [catégorie, nom, prix FCFA, durée en minutes, zone]
        $services = [
            ['Coiffure hommes', '[Démo] Coupe homme', 3000, 30, ZoneSalon::Mixte],
            ['Coiffure femmes', '[Démo] Coupe femme', 5000, 45, ZoneSalon::Mixte],
            ['Coiffure enfants', '[Démo] Coupe enfant', 2500, 30, ZoneSalon::Mixte],
            ['Tresses', '[Démo] Tresses simples', 10000, 180, ZoneSalon::Femmes],
            ['Massages', '[Démo] Massage relaxant', 15000, 60, ZoneSalon::Femmes],
            ['Manucure', '[Démo] Manucure simple', 5000, 45, ZoneSalon::Femmes],
            ['Pédicure', '[Démo] Pédicure simple', 6000, 60, ZoneSalon::Femmes],
            ['Soins du visage', '[Démo] Soin du visage', 12000, 60, ZoneSalon::Femmes],
        ];

        $crees = [];

        foreach ($services as [$categorie, $nom, $prix, $duree, $zone]) {
            // $categorieId = CategorieService::where('nom', $categorie)->firstOrFail()->id;

            $categorieId = CategorieService::firstOrCreate(
                ['nom' => $categorie],
                ['ordre' => (int) CategorieService::max('ordre') + 1],
                )->id;

            $crees[$nom] = Service::updateOrCreate(
                ['categorie_service_id' => $categorieId, 'nom' => $nom],
                ['prix' => $prix, 'duree_minutes' => $duree, 'zone' => $zone, 'is_active' => true],
            );
        }

        $forfaits = [
            ['[Démo] Forfait mains et pieds', 10000, ['[Démo] Manucure simple', '[Démo] Pédicure simple']],
            ['[Démo] Forfait détente', 24000, ['[Démo] Massage relaxant', '[Démo] Soin du visage']],
        ];

        foreach ($forfaits as [$nom, $prix, $noms]) {
            $forfait = Forfait::updateOrCreate(
                ['nom' => $nom],
                ['prix' => $prix, 'is_active' => true],
            );

            $forfait->services()->sync(array_map(fn (string $n) => $crees[$n]->id, $noms));
        }
    }
}