<?php

namespace Database\Factories;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => RoleUtilisateur::Professionnel,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function administratrice(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::Administratrice]);
    }

    public function receptionniste(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::Receptionniste]);
    }

    public function professionnel(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::Professionnel]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}