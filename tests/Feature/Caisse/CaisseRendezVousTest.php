<?php

namespace Tests\Feature\Caisse;

use App\Enums\StatutRendezVous;
use App\Models\RendezVous;
use App\Services\RendezVousService;

class CaisseRendezVousTest extends CaisseTestCase
{
    /** Rendez-vous réel : lundi 9 novembre, 10:00, une coupe à 5 000 FCFA. */
    private function reel(): RendezVous
    {
        return app(RendezVousService::class)->creer(
            $this->input(['client_id' => $this->client->id]),
            $this->receptionniste,
        );
    }

    public function test_un_rendez_vous_paye_ne_peut_plus_changer_de_prix(): void
    {
        $rdv = $this->reel();
        $this->payer($rdv, 2000);

        $this->assertValidationError('services', fn () => app(RendezVousService::class)->modifier(
            $rdv,
            $this->input(['services' => [$this->coupe->id, $this->massage->id], 'zone' => 'femmes']),
            $this->receptionniste,
        ));

        $this->assertSame(5000, $rdv->fresh()->prix_prevu);
        $this->assertCount(1, $rdv->fresh()->lignes);
    }

    public function test_un_rendez_vous_paye_peut_etre_deplace_au_meme_prix(): void
    {
        $rdv = $this->reel();
        $this->payer($rdv, 2000);

        app(RendezVousService::class)->modifier($rdv, $this->input(['heure_debut' => '14:00']), $this->receptionniste);

        $this->assertSame('14:00:00', $rdv->fresh()->heure_debut);
        $this->assertSame(5000, $rdv->fresh()->prix_prevu);
    }

    public function test_apres_annulation_du_paiement_le_prix_peut_changer(): void
    {
        $rdv = $this->reel();
        $paiement = $this->payer($rdv, 2000);
        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        app(RendezVousService::class)->modifier(
            $rdv,
            $this->input(['services' => [$this->coupe->id, $this->massage->id], 'zone' => 'femmes']),
            $this->receptionniste,
        );

        $this->assertSame(20000, $rdv->fresh()->prix_prevu);
    }

    public function test_un_rendez_vous_sans_paiement_change_de_prix_librement(): void
    {
        $rdv = $this->reel();

        app(RendezVousService::class)->modifier(
            $rdv,
            $this->input(['services' => [$this->coupe->id, $this->massage->id], 'zone' => 'femmes']),
            $this->receptionniste,
        );

        $this->assertSame(20000, $rdv->fresh()->prix_prevu);
    }

    public function test_un_rendez_vous_qui_a_recu_de_l_argent_ne_peut_pas_etre_annule(): void
    {
        $rdv = $this->reel();
        $paiement = $this->payer($rdv, 2000);

        $this->assertValidationError('statut', fn () => app(RendezVousService::class)->changerStatut(
            $rdv,
            StatutRendezVous::Annule,
            $this->admin,
        ));
        $this->assertSame(StatutRendezVous::Confirme, $rdv->fresh()->statut);

        // Une fois le paiement annulé (remboursement), l'annulation du rendez-vous passe.
        $this->caisse()->annuler($paiement, 'Remboursement client', $this->admin);

        app(RendezVousService::class)->changerStatut($rdv, StatutRendezVous::Annule, $this->admin);

        $this->assertSame(StatutRendezVous::Annule, $rdv->fresh()->statut);
    }

    public function test_un_rendez_vous_sans_paiement_s_annule_normalement(): void
    {
        $rdv = $this->reel();

        app(RendezVousService::class)->changerStatut($rdv, StatutRendezVous::Annule, $this->receptionniste);

        $this->assertSame(StatutRendezVous::Annule, $rdv->fresh()->statut);
    }
}