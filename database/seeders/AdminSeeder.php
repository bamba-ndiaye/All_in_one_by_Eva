<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('salon.admin');
        $email = Str::lower(trim((string) ($config['email'] ?? '')));
        $password = (string) ($config['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->command?->warn('ADMIN_EMAIL et ADMIN_PASSWORD sont absents de .env : administratrice non créée.');

            return;
        }

        if (mb_strlen($password) < 10) {
            $this->command?->error('ADMIN_PASSWORD doit contenir au moins 10 caractères : administratrice non créée.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Le compte {$email} existe déjà : rien n'a été modifié.");

            return;
        }

        $user = new User([
            'name' => blank($config['name']) ? 'Administratrice' : $config['name'],
            'email' => $email,
            'password' => $password,
        ]);

        $user->forceFill([
            'role' => RoleUtilisateur::Administratrice,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->command?->info("Administratrice créée : {$email}");
    }
}