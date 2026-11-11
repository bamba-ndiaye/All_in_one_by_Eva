<?php

namespace Tests\Feature\Caisse;

use App\Models\Paiement;
use App\Models\RendezVous;
use App\Models\User;
use Database\Seeders\DemoPaiementsSeeder;
use Tests\Feature\Agenda\AgendaTestCase;

class DemoPaiementsSeederTest extends AgendaTestCase
{
    public function test_le_seeder_encaisse_un_rendez_vous_en_entier_et_un_en_partie_et_reste_repetable(): void
    {
        $this->seed(DemoPaiementsSeeder::class);

        $this->assertSame(2, Paiement::count());
        $this->assertSame(1, RendezVous::impayes()->count());

        $this->seed(DemoPaiementsSeeder::class);

        $this->assertSame(2, Paiement::count());
    }

    public function test_le_seeder_ne_fait_rien_sans_administratrice(): void
    {
        User::query()->delete();

        $this->seed(DemoPaiementsSeeder::class);

        $this->assertSame(0, Paiement::count());
    }
}