<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Jeu de données pour les tests manuels (docs/GUIDE-DE-TESTS.md).
 *
 *   php artisan db:seed --class=JeuDeTestSeeder
 *
 * Rejouable : ne crée pas de doublons. Refusé en production.
 * L'administratrice vient de .env (ADMIN_EMAIL / ADMIN_PASSWORD), comme d'habitude.
 */
class JeuDeTestSeeder extends Seeder
{
    /** Comptes de test : [rôle, nom, email, mot de passe, employé lié (nom, prénom) ou null]. */
    public const COMPTES = [
        [RoleUtilisateur::Receptionniste, 'Réception Test', 'reception@test.local', 'Reception-2026', null],
        [RoleUtilisateur::Professionnel, 'Awa Fall (test)', 'awa.fall@test.local', 'Coiffeuse-2026', ['[Démo] Fall', 'Awa']],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('JeuDeTestSeeder est interdit en production : rien n\'a été fait.');

            return;
        }

        $this->call([
            AdminSeeder::class,
            CategoriesServicesSeeder::class,
            ModesPaiementSeeder::class,
            CategoriesDepensesSeeder::class,
            DemoServicesSeeder::class,
            DemoClientsSeeder::class,
            DemoEmployesSeeder::class,
            DemoRendezVousSeeder::class,
            DemoPaiementsSeeder::class,
            DemoDepensesSeeder::class,
            DemoProduitsSeeder::class,
            DemoFideliteSeeder::class,
        ]);

        foreach (self::COMPTES as [$role, $nom, $email, $motDePasse, $employe]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill(['name' => $nom, 'password' => $motDePasse]);
            $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

            if ($employe) {
                Employe::where('nom', $employe[0])->where('prenom', $employe[1])->update(['user_id' => $user->id]);
            }

            $this->command?->info("Compte de test : {$email} / {$motDePasse} ({$role->label()})");
        }
    }
}
