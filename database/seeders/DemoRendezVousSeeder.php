<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutRendezVous;
use App\Models\Client;
use App\Models\Employe;
use App\Models\Forfait;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Services\RendezVousService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DemoRendezVousSeeder extends Seeder
{
    private const MARQUE = '[Démo]';

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

        if (RendezVous::where('commentaire', self::MARQUE)->exists()) {
            $this->command?->info('Les rendez-vous de démonstration existent déjà : rien n\'a été modifié.');

            return;
        }

        $this->call([DemoClientsSeeder::class, DemoEmployesSeeder::class]);

        // [jours ouvrés d'écart, heure, téléphone du client, employé, prestations, forfaits, zone]
        $aVenir = [
            [1, '10:00', '+221770000101', '[Démo] Fall', ['[Démo] Coupe femme'], [], 'mixte'],
            [1, '11:00', '+221770000102', '[Démo] Diop', [], ['[Démo] Forfait mains et pieds'], 'femmes'],
            [1, '15:00', '+221770000106', '[Démo] Fall', ['[Démo] Coupe homme'], [], 'mixte'],
            [2, '09:30', '+221760000103', '[Démo] Ndiaye', ['[Démo] Massage relaxant'], [], 'femmes'],
            [3, '14:00', '+221750000105', '[Démo] Fall', ['[Démo] Tresses simples'], [], 'femmes'],
            [3, '10:00', '+221780000104', '[Démo] Diop', ['[Démo] Soin du visage'], [], 'femmes'],
        ];

        $passes = [
            [-2, '10:00', '+221770000107', '[Démo] Fall', ['[Démo] Coupe enfant'], [], 'mixte'],
            [-2, '11:00', '+221750000105', '[Démo] Diop', ['[Démo] Manucure simple'], [], 'femmes'],
        ];

        $this->creer($aVenir, $admin, false);
        $this->creer($passes, $admin, true);
    }

    /**
     * @param  list<array<int, mixed>>  $rendezVous
     */
    private function creer(array $rendezVous, User $admin, bool $termine): void
    {
        $service = app(RendezVousService::class);

        foreach ($rendezVous as [$decalage, $heure, $telephone, $employeNom, $prestations, $forfaits, $zone]) {
            $client = Client::where('telephone', $telephone)->first();
            $employe = Employe::where('nom', $employeNom)->first();

            if (! $client || ! $employe) {
                continue;
            }

            try {
                $rdv = $service->creer([
                    'client_id' => $client->id,
                    'employe_id' => $employe->id,
                    'date' => $this->jourOuvre($decalage)->toDateString(),
                    'heure_debut' => $heure,
                    'zone' => $zone,
                    'services' => Service::whereIn('nom', $prestations)->pluck('id')->all(),
                    'forfaits' => Forfait::whereIn('nom', $forfaits)->pluck('id')->all(),
                    'statut' => 'confirme',
                    'commentaire' => self::MARQUE,
                ], $admin);

                if ($termine) {
                    foreach ([StatutRendezVous::ClientArrive, StatutRendezVous::EnCours, StatutRendezVous::Termine] as $statut) {
                        $rdv = $service->changerStatut($rdv, $statut, $admin);
                    }
                }
            } catch (ValidationException $e) {
                $this->command?->warn('Rendez-vous de démonstration ignoré : '.collect($e->errors())->flatten()->first());
            }
        }
    }

    /** Jour ouvré (du lundi au samedi) situé à N jours ouvrés d'aujourd'hui, en avant ou en arrière. */
    private function jourOuvre(int $decalage): Carbon
    {
        $jour = now()->startOfDay();
        $pas = $decalage <=> 0;

        for ($i = 0; $i < abs($decalage); $i++) {
            do {
                $jour->addDays($pas);
            } while ($jour->dayOfWeekIso === 7);
        }

        return $jour;
    }
}