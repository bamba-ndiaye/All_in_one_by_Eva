<?php

namespace Database\Factories;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produit>
 */
class ProduitFactory extends Factory
{
    protected $model = Produit::class;

    public function definition(): array
    {
        return [
            'nom' => 'Produit '.fake()->unique()->numberBetween(1, 99999),
            'reference' => null,
            'unite' => 'flacon',
            'prix_achat' => 2500,
            'prix_vente' => null,
            'seuil_alerte' => 2,
            'is_active' => true,
        ];
    }
}
