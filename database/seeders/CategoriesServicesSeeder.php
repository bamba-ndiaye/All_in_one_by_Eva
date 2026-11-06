<?php

namespace Database\Seeders;

use App\Models\CategorieService;
use Illuminate\Database\Seeder;

class CategoriesServicesSeeder extends Seeder
{
    public const CATEGORIES = [
        'Coiffure hommes',
        'Coiffure femmes',
        'Coiffure enfants',
        'Tresses',
        'Coloration',
        'Soins capillaires',
        'Massages',
        'Manucure',
        'Pédicure',
        'Soins du visage',
        'Forfaits combinés',
    ];

    public function run(): void
    {
        if (CategorieService::exists()) {
            $this->command?->info('Les catégories de prestations existent déjà : rien n\'a été modifié.');

            return;
        }

        foreach (self::CATEGORIES as $index => $nom) {
            CategorieService::create(['nom' => $nom, 'ordre' => $index + 1]);
        }

        $this->command?->info(count(self::CATEGORIES).' catégories de prestations créées.');
    }
}