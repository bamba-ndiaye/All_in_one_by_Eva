<?php

namespace Tests\Feature\Caisse;

use App\Models\HistoriqueAction;
use App\Models\Paiement;
use Illuminate\Auth\Access\AuthorizationException;

class CorrectionPaiementTest extends CaisseTestCase
{
    public function test_corriger_le_montant_et_le_mode(): void
    {
        $rdv = $this->rdv();
        $paiement = $this->payer($rdv, 10000);   // en espèces, par erreur

        $corrige = $this->caisse()->corriger($paiement, [
            'montant' => 8000,
            'mode_paiement_id' => $this->wave->id,
            'reference' => 'WV123',
        ], 'Mauvais montant et mauvais mode', $this->admin);

        $this->assertSame(8000, $corrige->montant);
        $this->assertSame($this->wave->id, $corrige->mode_paiement_id);
        $this->assertSame('WV123', $corrige->reference);
        $this->assertSame(12000, $corrige->reste_apres);
        $this->assertSame(8000, $rdv->montantPaye());

        // Paiement d'origine, annulation, paiement corrigé : trois lignes, aucune réécrite.
        $this->assertSame(3, Paiement::count());
        $this->assertSame(10000, $paiement->fresh()->montant);
        $this->assertTrue($paiement->fresh()->estAnnule());
        $this->assertSame(1, HistoriqueAction::where('action', 'paiement.corrige')->count());
    }

    public function test_une_correction_refusee_ne_laisse_rien_derriere_elle(): void
    {
        $rdv = $this->rdv();
        $paiement = $this->payer($rdv, 10000);

        // 25 000 dépasse le prix du rendez-vous : le nouveau paiement est refusé.
        $this->assertValidationError('montant', fn () => $this->caisse()->corriger($paiement, [
            'montant' => 25000,
            'mode_paiement_id' => $this->wave->id,
        ], 'Erreur de saisie', $this->admin));

        $this->assertSame(1, Paiement::count());
        $this->assertFalse($paiement->fresh()->estAnnule());
        $this->assertSame(10000, $rdv->montantPaye());
    }

    public function test_une_correction_avec_motif_invalide_ne_change_rien(): void
    {
        $paiement = $this->payer($this->rdv(), 10000);

        $this->assertValidationError('motif', fn () => $this->caisse()->corriger($paiement, [
            'montant' => 8000,
            'mode_paiement_id' => $this->wave->id,
        ], 'x', $this->admin));

        $this->assertSame(1, Paiement::count());
    }

    public function test_la_correction_garde_le_meme_rendez_vous_et_le_meme_client(): void
    {
        $rdv = $this->rdv();
        $paiement = $this->payer($rdv, 10000);

        $corrige = $this->caisse()->corriger($paiement, ['montant' => 9000, 'mode_paiement_id' => $this->especes->id], 'Erreur de saisie', $this->admin);

        $this->assertSame($rdv->id, $corrige->rendez_vous_id);
        $this->assertSame($this->client->id, $corrige->client_id);
    }

    public function test_corriger_une_vente_sans_rendez_vous(): void
    {
        $vente = $this->caisse()->enregistrer([
            'client_id' => $this->client->id,
            'libelle' => 'Vente de shampoing',
            'montant' => 3000,
            'mode_paiement_id' => $this->especes->id,
        ], $this->receptionniste);

        $corrige = $this->caisse()->corriger($vente, ['montant' => 3500, 'mode_paiement_id' => $this->especes->id], 'Prix erroné', $this->admin);

        $this->assertNull($corrige->rendez_vous_id);
        $this->assertSame('Vente de shampoing', $corrige->libelle);
        $this->assertSame(3500, $corrige->montant_total);
    }

    public function test_la_receptionniste_ne_peut_pas_corriger(): void
    {
        $paiement = $this->payer($this->rdv(), 10000);

        $this->expectException(AuthorizationException::class);

        $this->caisse()->corriger($paiement, ['montant' => 8000, 'mode_paiement_id' => $this->wave->id], 'Erreur de saisie', $this->receptionniste);
    }
}