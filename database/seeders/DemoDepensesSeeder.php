<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\User;
use App\Services\DepenseService;
use Illuminate\Database\Seeder;

class DemoDepensesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', RoleUtilisateur::Administratrice->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $admin) {
            $this->command?->warn("Aucune administratrice : lancez d'abord AdminSeeder.");

            return;
        }

        if (Depense::where('description', 'like', '[Démo]%')->exists()) {
            $this->command?->info('Les dépenses de démonstration existent déjà : rien n\'a été modifié.');

            return;
        }

        $this->call(CategoriesDepensesSeeder::class);

        // [jours en arrière, catégorie, montant FCFA, description]
        $depenses = [
            [0, 'Achat de produits', 25000, '[Démo] Shampoings et soins'],
            [2, 'Électricité', 18000, "[Démo] Facture d'électricité"],
            [5, 'Eau', 6500, "[Démo] Facture d'eau"],
            [9, 'Entretien du matériel', 12000, '[Démo] Réparation du sèche-cheveux'],
        ];

        $service = app(DepenseService::class);

        foreach ($depenses as [$jours, $categorie, $montant, $description]) {
            $categorieId = CategorieDepense::firstOrCreate(
                ['nom' => $categorie],
                ['ordre' => (int) CategorieDepense::max('ordre') + 1],
            )->id;

            $service->creer([
                'categorie_depense_id' => $categorieId,
                'montant' => $montant,
                'date' => now()->subDays($jours)->toDateString(),
                'description' => $description,
            ], null, $admin);
        }
    }
}