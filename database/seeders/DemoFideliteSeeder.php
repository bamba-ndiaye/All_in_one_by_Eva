<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\Recompense;
use App\Models\User;
use App\Services\FideliteService;
use Illuminate\Database\Seeder;

class DemoFideliteSeeder extends Seeder
{
    public function run(FideliteService $fidelite): void
    {
        $admin = User::where('role', RoleUtilisateur::Administratrice->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $admin) {
            $this->command?->warn("Aucune administratrice : lancez d'abord AdminSeeder.");

            return;
        }

        if (Recompense::exists()) {
            $this->command?->info('Des récompenses existent déjà : rien n\'a été modifié.');

            return;
        }

        // [nom, points, précision]
        $recompenses = [
            ['Pose de vernis offerte', 20, null],
            ['Brushing offert', 50, 'Cheveux courts et mi-longs'],
            ['Manucure complète offerte', 80, null],
            ['Réduction de 10 000 FCFA', 120, 'Sur une prestation de coiffure'],
        ];

        foreach ($recompenses as [$nom, $points, $description]) {
            $fidelite->enregistrerRecompense(null, ['nom' => $nom, 'points' => $points, 'description' => $description], $admin);
        }

        $this->command?->info(count($recompenses).' récompenses de démonstration créées.');
    }
}
