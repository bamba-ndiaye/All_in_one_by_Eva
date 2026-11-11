<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Employe;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

abstract class AgendaTestCase extends TestCase
{
    use RefreshDatabase;

    protected Client $client;
    protected Employe $employe;
    protected Service $coupe;     // 45 min, 5 000 FCFA, zone mixte
    protected Service $massage;   // 60 min, 15 000 FCFA, espace femmes
    protected User $admin;
    protected User $receptionniste;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Lundi 9 novembre 2026, 8 h.
        $this->travelTo(Carbon::parse('2026-11-09 08:00:00'));

        $this->coupe = Service::factory()->create(['nom' => 'Coupe test', 'prix' => 5000, 'duree_minutes' => 45]);
        $this->massage = Service::factory()->femmes()->create(['nom' => 'Massage test', 'prix' => 15000, 'duree_minutes' => 60]);

        $this->employe = Employe::factory()->create(['nom' => 'Fall', 'prenom' => 'Awa']);
        $this->employe->services()->sync([$this->coupe->id, $this->massage->id]);
        $this->employe->disponibilites()->createMany([
            ['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '13:00'],
            ['jour_semaine' => 1, 'heure_debut' => '14:00', 'heure_fin' => '18:00'],
        ]);

        $this->client = Client::factory()->create();
        $this->admin = User::factory()->administratrice()->create();
        $this->receptionniste = User::factory()->receptionniste()->create();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** Données d'une réservation valide : lundi 9 novembre, 10:00, une coupe. */
    protected function input(array $override = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'employe_id' => $this->employe->id,
            'date' => '2026-11-09',
            'heure_debut' => '10:00',
            'zone' => 'mixte',
            'services' => [$this->coupe->id],
            'forfaits' => [],
        ], $override);
    }

    protected function assertValidationError(string $champ, callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($champ, $e->errors(), 'Erreurs reçues : '.json_encode($e->errors()));

            return;
        }

        $this->fail("Aucune erreur de validation sur « {$champ} ».");
    }
}