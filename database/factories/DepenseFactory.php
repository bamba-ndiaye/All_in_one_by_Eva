<?php

namespace Database\Factories;

use App\Models\CategorieDepense;
use App\Models\Depense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depense>
 */
class DepenseFactory extends Factory
{
    protected $model = Depense::class;

    public function definition(): array
    {
        return [
            'categorie_depense_id' => CategorieDepense::factory(),
            'montant' => fake()->numberBetween(2, 100) * 500,
            'date' => '2026-11-09',
            'description' => fake()->sentence(3),
        ];
    }
}