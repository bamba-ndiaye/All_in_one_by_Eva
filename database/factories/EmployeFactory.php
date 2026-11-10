<?php

namespace Database\Factories;

use App\Enums\TypeCommission;
use App\Models\Employe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employe>
 */
class EmployeFactory extends Factory
{
    protected $model = Employe::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'telephone' => fake()->numerify('77 ### ## ##'),
            'fonction' => fake()->randomElement(['Coiffeuse', 'Esthéticienne', 'Masseuse', 'Manucure']),
            'specialites' => fake()->sentence(3),
            'commission_type' => null,
            'commission_valeur' => 0,
            'is_active' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function avecCommission(TypeCommission $type, int $valeur): static
    {
        return $this->state(fn () => ['commission_type' => $type, 'commission_valeur' => $valeur]);
    }
}