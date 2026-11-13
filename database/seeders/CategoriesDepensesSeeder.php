<?php

namespace Database\Seeders;

use App\Models\CategorieDepense;
use Illuminate\Database\Seeder;

class CategoriesDepensesSeeder extends Seeder
{
    public const CATEGORIES = [
        'Achat de produits',
        'Loyer',
        'Électricité',
        'Eau',
        'Salaires et commissions',
        'Entretien du matériel',
        'Autres dépenses',
    ];

    public function run(): void
    {
        if (CategorieDepense::exists()) {
            $this->command?->info("Les catégories de dépenses existent déjà : rien n'a été modifié.");

            return;
        }

        foreach (self::CATEGORIES as $index => $nom) {
            CategorieDepense::create(['nom' => $nom, 'ordre' => $index + 1]);
        }

        $this->command?->info(count(self::CATEGORIES).' catégories de dépenses créées.');
    }
}