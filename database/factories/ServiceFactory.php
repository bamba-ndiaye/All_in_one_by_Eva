<?php

namespace Database\Factories;

use App\Enums\ZoneSalon;
use App\Models\CategorieService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'categorie_service_id' => CategorieService::factory(),
            'nom' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'prix' => fake()->numberBetween(2, 40) * 500,
            'duree_minutes' => fake()->randomElement([30, 45, 60, 90, 120]),
            'zone' => ZoneSalon::Mixte,
            'is_active' => true,
        ];
    }

    public function femmes(): static
    {
        return $this->state(fn () => ['zone' => ZoneSalon::Femmes]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}