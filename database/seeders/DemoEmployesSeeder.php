<?php

namespace Database\Seeders;

use App\Enums\TypeCommission;
use App\Models\Employe;
use App\Models\Service;
use Illuminate\Database\Seeder;

class DemoEmployesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoServicesSeeder::class);

        // [nom, prénom, fonction, téléphone, noms des prestations, type de commission, valeur]
        $equipe = [
            ['[Démo] Fall', 'Awa', 'Coiffeuse', '77 000 00 01',
                ['[Démo] Coupe femme', '[Démo] Coupe homme', '[Démo] Coupe enfant', '[Démo] Tresses simples'],
                TypeCommission::Pourcentage, 20],
            ['[Démo] Diop', 'Mariama', 'Esthéticienne', '77 000 00 02',
                ['[Démo] Manucure simple', '[Démo] Pédicure simple', '[Démo] Soin du visage'],
                TypeCommission::Fixe, 1000],
            ['[Démo] Ndiaye', 'Fatou', 'Masseuse', '77 000 00 03',
                ['[Démo] Massage relaxant', '[Démo] Soin du visage'],
                null, 0],
        ];

        foreach ($equipe as [$nom, $prenom, $fonction, $telephone, $prestations, $type, $valeur]) {
            $employe = Employe::updateOrCreate(
                ['nom' => $nom, 'prenom' => $prenom],
                [
                    'fonction' => $fonction,
                    'telephone' => $telephone,
                    'commission_type' => $type,
                    'commission_valeur' => $valeur,
                    'is_active' => true,
                ],
            );

            $employe->services()->sync(Service::whereIn('nom', $prestations)->pluck('id')->all());

            $employe->disponibilites()->delete();

            $plages = [];
            foreach ([1, 2, 3, 4, 5] as $jour) {
                $plages[] = ['jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '13:00'];
                $plages[] = ['jour_semaine' => $jour, 'heure_debut' => '14:00', 'heure_fin' => '18:00'];
            }
            $plages[] = ['jour_semaine' => 6, 'heure_debut' => '09:00', 'heure_fin' => '14:00'];

            $employe->disponibilites()->createMany($plages);
        }
    }
}