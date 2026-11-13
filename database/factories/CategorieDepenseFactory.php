<?php

namespace Database\Factories;

use App\Models\CategorieDepense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategorieDepense>
 */
class CategorieDepenseFactory extends Factory
{
    protected $model = CategorieDepense::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->words(2, true),
            'ordre' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}