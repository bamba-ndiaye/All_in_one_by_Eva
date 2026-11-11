<?php

namespace Tests\Feature\Caisse;

use App\Enums\StatutRendezVous;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\CaisseService;
use Tests\Feature\Agenda\AgendaTestCase;

abstract class CaisseTestCase extends AgendaTestCase
{
    protected ModePaiement $especes;
    protected ModePaiement $wave;

    protected function setUp(): void
    {
        parent::setUp();

        $this->especes = ModePaiement::factory()->create(['nom' => 'Espèces', 'ordre' => 1]);
        $this->wave = ModePaiement::factory()->create(['nom' => 'Wave', 'ordre' => 2]);
    }

    protected function caisse(): CaisseService
    {
        return app(CaisseService::class);
    }

    /** Rendez-vous de 20 000 FCFA, terminé par défaut. */
    protected function rdv(StatutRendezVous $statut = StatutRendezVous::Termine, array $override = []): RendezVous
    {
        return RendezVous::factory()->create(array_merge([
            'employe_id' => $this->employe->id,
            'client_id' => $this->client->id,
            'statut' => $statut,
            'prix_prevu' => 20000,
        ], $override));
    }

    protected function payer(
        RendezVous $rdv,
        int $montant,
        ?ModePaiement $mode = null,
        ?User $user = null,
        array $extra = [],
    ): Paiement {
        return $this->caisse()->enregistrer(array_merge([
            'rendez_vous_id' => $rdv->id,
            'montant' => $montant,
            'mode_paiement_id' => ($mode ?? $this->especes)->id,
        ], $extra), $user ?? $this->receptionniste);
    }
}