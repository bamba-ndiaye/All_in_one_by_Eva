<?php

namespace Database\Factories;

use App\Enums\StatutRendezVous;
use App\Enums\ZoneSalon;
use App\Models\Client;
use App\Models\Employe;
use App\Models\RendezVous;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RendezVous>
 */
class RendezVousFactory extends Factory
{
    protected $model = RendezVous::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'employe_id' => Employe::factory(),
            'date' => '2026-11-09',
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'zone' => ZoneSalon::Mixte,
            'statut' => StatutRendezVous::Confirme,
            'prix_prevu' => 5000,
            'commentaire' => null,
        ];
    }

    public function statut(StatutRendezVous $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }

    public function le(string $date, string $debut, string $fin): static
    {
        return $this->state(fn () => ['date' => $date, 'heure_debut' => $debut, 'heure_fin' => $fin]);
    }
}