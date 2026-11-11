<?php

namespace Tests\Feature\Agenda;

use App\Enums\StatutRendezVous;
use App\Models\RendezVous;
use App\Models\User;
use Database\Seeders\DemoRendezVousSeeder;

class DemoRendezVousSeederTest extends AgendaTestCase
{
    public function test_le_seeder_cree_huit_rendez_vous_dont_deux_termines_et_reste_repetable(): void
    {
        // Lundi 9 novembre 2026 : les jours ouvrés choisis respectent les horaires de démonstration.
        $this->seed(DemoRendezVousSeeder::class);

        $this->assertSame(8, RendezVous::count());
        $this->assertSame(2, RendezVous::where('statut', StatutRendezVous::Termine->value)->count());
        $this->assertSame(6, RendezVous::where('statut', StatutRendezVous::Confirme->value)->count());

        $this->seed(DemoRendezVousSeeder::class);

        $this->assertSame(8, RendezVous::count());
    }

    public function test_le_seeder_ne_fait_rien_sans_administratrice(): void
    {
        User::query()->delete();

        $this->seed(DemoRendezVousSeeder::class);

        $this->assertSame(0, RendezVous::count());
    }
}