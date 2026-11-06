<?php

namespace Database\Factories;

use App\Models\CategorieService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategorieService>
 */
class CategorieServiceFactory extends Factory
{
    protected $model = CategorieService::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->words(2, true),
            'ordre' => fake()->numberBetween(1, 50),
        ];
    }
}
