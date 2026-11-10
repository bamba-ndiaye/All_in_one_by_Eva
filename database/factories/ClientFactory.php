<?php

namespace Database\Factories;

use App\Enums\CanalCommunication;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'prenom' => fake()->firstName(),
            'nom' => fake()->lastName(),
            'telephone' => '+22177'.fake()->unique()->numerify('#######'),
            'whatsapp' => null,
            'email' => fake()->optional()->safeEmail(),
            'date_naissance' => fake()->dateTimeBetween('-60 years', '-5 years')->format('Y-m-d'),
            'canal_prefere' => CanalCommunication::Whatsapp,
            'consentement_promo' => false,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function avecConsentement(): static
    {
        return $this->state(fn () => [
            'consentement_promo' => true,
            'date_consentement' => now(),
        ]);
    }

    public function sansNaissance(): static
    {
        return $this->state(fn () => ['date_naissance' => null]);
    }
}