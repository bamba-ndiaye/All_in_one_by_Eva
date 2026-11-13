<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Enums\TypeMouvementStock;
use App\Models\Produit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

class DemoProduitsSeeder extends Seeder
{
    public function run(StockService $stock): void
    {
        $admin = User::where('role', RoleUtilisateur::Administratrice->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $admin) {
            $this->command?->warn("Aucune administratrice : lancez d'abord AdminSeeder.");

            return;
        }

        if (Produit::where('reference', 'like', 'DEMO-%')->exists()) {
            $this->command?->info('Les produits de démonstration existent déjà : rien n\'a été modifié.');

            return;
        }

        // [référence, nom, unité, prix d'achat, prix de vente, seuil, quantité initiale]
        $produits = [
            ['DEMO-01', 'Shampoing kératine 500 ml', 'flacon', 4500, 7500, 3, 12],
            ['DEMO-02', 'Après-shampoing karité', 'flacon', 3500, 6000, 3, 2],
            ['DEMO-03', 'Mèches brésiliennes 18"', 'paquet', 9000, 15000, 5, 20],
            ['DEMO-04', 'Vernis à ongles rouge', 'flacon', 1500, 3000, 4, 9],
            ['DEMO-05', 'Dissolvant sans acétone', 'flacon', 1200, null, 2, 1],
            ['DEMO-06', 'Cire épilatoire tiède', 'pot', 6000, null, 2, 0],
            ['DEMO-07', 'Serviettes jetables', 'paquet', 2500, null, 5, 14],
            ['DEMO-08', 'Huile de coco pure', 'flacon', 2000, 4000, 2, 6],
        ];

        foreach ($produits as [$ref, $nom, $unite, $achat, $vente, $seuil, $quantite]) {
            $stock->creer([
                'reference' => $ref,
                'nom' => $nom,
                'unite' => $unite,
                'prix_achat' => $achat,
                'prix_vente' => $vente,
                'seuil_alerte' => $seuil,
            ], $quantite, $admin);
        }

        $shampoing = Produit::where('reference', 'DEMO-01')->first();
        $stock->mouvement($shampoing, TypeMouvementStock::Utilisation, 2, 'Soins de la semaine', $admin);
        $stock->mouvement($shampoing, TypeMouvementStock::Vente, 1, null, $admin);

        $this->command?->info(count($produits).' produits de démonstration créés (dont 3 en stock bas).');
    }
}
