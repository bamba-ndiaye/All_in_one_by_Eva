<?php

namespace Database\Factories;

use App\Models\Forfait;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Forfait>
 */
class ForfaitFactory extends Factory
{
    protected $model = Forfait::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'prix' => fake()->numberBetween(5, 60) * 500,
            'is_active' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}