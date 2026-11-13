<?php

namespace Tests\Feature\Depenses;

use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\User;
use App\Services\DepenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

abstract class DepenseTestCase extends TestCase
{
    use RefreshDatabase;

    protected CategorieDepense $loyer;
    protected CategorieDepense $produits;
    protected User $admin;
    protected User $receptionniste;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00'));

        $this->loyer = CategorieDepense::factory()->create(['nom' => 'Loyer', 'ordre' => 1]);
        $this->produits = CategorieDepense::factory()->create(['nom' => 'Achat de produits', 'ordre' => 2]);

        $this->admin = User::factory()->administratrice()->create();
        $this->receptionniste = User::factory()->receptionniste()->create();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** Données d'une dépense valide : loyer de novembre, 150 000 FCFA, aujourd'hui. */
    protected function donnees(array $override = []): array
    {
        return array_merge([
            'categorie_depense_id' => $this->loyer->id,
            'montant' => 150000,
            'date' => '2026-11-09',
            'description' => 'Loyer de novembre',
        ], $override);
    }

    protected function depenses(): DepenseService
    {
        return app(DepenseService::class);
    }

    protected function creer(array $override = [], ?UploadedFile $fichier = null, ?User $user = null): Depense
    {
        return $this->depenses()->creer($this->donnees($override), $fichier, $user ?? $this->admin);
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