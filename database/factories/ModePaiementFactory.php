<?php

namespace Database\Factories;

use App\Models\ModePaiement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModePaiement>
 */
class ModePaiementFactory extends Factory
{
    protected $model = ModePaiement::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->words(2, true),
            'ordre' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}