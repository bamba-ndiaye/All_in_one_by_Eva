<?php

namespace Database\Seeders;

use App\Models\ModePaiement;
use Illuminate\Database\Seeder;

class ModesPaiementSeeder extends Seeder
{
    public const MODES = ['Espèces', 'Wave', 'Orange Money', 'Carte bancaire', 'Autre'];

    public function run(): void
    {
        if (ModePaiement::exists()) {
            $this->command?->info("Les modes de paiement existent déjà : rien n'a été modifié.");

            return;
        }

        foreach (self::MODES as $index => $nom) {
            ModePaiement::create(['nom' => $nom, 'ordre' => $index + 1]);
        }

        $this->command?->info(count(self::MODES).' modes de paiement créés.');
    }
}