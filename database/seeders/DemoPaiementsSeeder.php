<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutRendezVous;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\CaisseService;
use Illuminate\Database\Seeder;

class DemoPaiementsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', RoleUtilisateur::Administratrice->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $admin) {
            $this->command?->warn("Aucune administratrice : lancez d'abord AdminSeeder.");

            return;
        }

        if (Paiement::exists()) {
            $this->command?->info("Des paiements existent déjà : rien n'a été modifié.");

            return;
        }

        $this->call(ModesPaiementSeeder::class);

        if (! RendezVous::where('commentaire', '[Démo]')->exists()) {
            $this->call(DemoRendezVousSeeder::class);
        }

        $termines = RendezVous::where('commentaire', '[Démo]')
            ->where('statut', StatutRendezVous::Termine->value)
            ->orderBy('id')
            ->get();

        $defaut = ModePaiement::actifs()->orderBy('ordre')->orderBy('id')->first();

        if ($termines->isEmpty() || ! $defaut) {
            $this->command?->warn('Aucun rendez-vous terminé à encaisser.');

            return;
        }

        $wave = ModePaiement::actifs()->where('nom', 'Wave')->first() ?? $defaut;
        $especes = ModePaiement::actifs()->where('nom', 'Espèces')->first() ?? $defaut;
        $caisse = app(CaisseService::class);

        foreach ($termines->take(2)->values() as $index => $rdv) {
            $complet = $index === 0;

            $caisse->enregistrer([
                'rendez_vous_id' => $rdv->id,
                'montant' => $complet ? $rdv->prix_prevu : intdiv($rdv->prix_prevu, 2),
                'mode_paiement_id' => ($complet ? $wave : $especes)->id,
                'reference' => $complet ? 'DEMO-WAVE-001' : null,
            ], $admin);
        }
    }
}