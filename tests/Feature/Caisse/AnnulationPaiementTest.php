<?php

namespace Tests\Feature\Caisse;

use App\Enums\TypePaiement;
use App\Models\HistoriqueAction;
use App\Models\Paiement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

class AnnulationPaiementTest extends CaisseTestCase
{
    public function test_une_annulation_est_une_ligne_negative_liee_au_paiement(): void
    {
        $rdv = $this->rdv();
        $paiement = $this->payer($rdv, 8000);

        $annulation = $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertSame(TypePaiement::Annulation, $annulation->type);
        $this->assertSame(-8000, $annulation->montant);
        $this->assertSame($paiement->id, $annulation->paiement_origine_id);
        $this->assertSame('Erreur de saisie', $annulation->motif);
        $this->assertSame($this->admin->id, $annulation->user_id);
        $this->assertSame($paiement->mode_paiement_id, $annulation->mode_paiement_id);
        $this->assertSame(20000, $annulation->reste_apres);

        // Le paiement d'origine n'a pas bougé.
        $this->assertSame(8000, $paiement->fresh()->montant);
        $this->assertTrue($paiement->fresh()->estAnnule());
        $this->assertSame(0, $rdv->montantPaye());
        $this->assertSame(2, Paiement::count());
    }

    public function test_l_annulation_est_journalisee_sans_le_motif(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);

        $this->caisse()->annuler($paiement, 'Cliente de passage secrète', $this->admin);

        $log = HistoriqueAction::where('action', 'paiement.annule')->firstOrFail();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($paiement->id, $log->sujet_id);
        $this->assertStringNotContainsString('secrète', json_encode([$log->description, $log->nouvelles_valeurs]));
    }

    public function test_seule_une_personne_autorisee_peut_annuler(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);

        try {
            $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->receptionniste);
            $this->fail("L'annulation aurait dû être refusée.");
        } catch (AuthorizationException) {
            $this->assertSame(1, Paiement::count());
        }

        $this->receptionniste->grantPermission('payments.correct');

        $this->assertSame(-8000, $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->receptionniste)->montant);
    }

    public function test_un_paiement_ne_s_annule_qu_une_fois(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);
        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertValidationError('paiement', fn () => $this->caisse()->annuler($paiement, 'Encore une fois', $this->admin));
        $this->assertSame(2, Paiement::count());
    }

    public function test_une_annulation_ne_s_annule_pas(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);
        $annulation = $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertValidationError('paiement', fn () => $this->caisse()->annuler($annulation, 'Annuler l\'annulation', $this->admin));
        $this->assertSame(2, Paiement::count());
    }

    public function test_le_motif_est_obligatoire(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);

        foreach (['', '   ', 'abc', str_repeat('a', 256)] as $motif) {
            $this->assertValidationError('motif', fn () => $this->caisse()->annuler($paiement, $motif, $this->admin));
        }

        $this->assertFalse($paiement->fresh()->estAnnule());
        $this->assertSame(1, Paiement::count());
    }

    public function test_la_base_refuse_une_seconde_annulation_du_meme_paiement(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);
        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->expectException(QueryException::class);

        Paiement::create([
            'client_id' => $paiement->client_id,
            'rendez_vous_id' => $paiement->rendez_vous_id,
            'type' => TypePaiement::Annulation,
            'paiement_origine_id' => $paiement->id,
            'mode_paiement_id' => $paiement->mode_paiement_id,
            'montant' => -8000,
            'montant_total' => 20000,
            'reste_apres' => 20000,
            'date' => '2026-11-09',
        ]);
    }

    public function test_apres_annulation_le_rendez_vous_peut_etre_encaisse_de_nouveau(): void
    {
        $rdv = $this->rdv();
        $paiement = $this->payer($rdv, 20000);
        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertSame(20000, $rdv->resteAPayer());
        $this->assertSame(20000, $this->payer($rdv, 20000)->montant);
        $this->assertSame(0, $rdv->resteAPayer());
    }

    public function test_une_vente_sans_rendez_vous_peut_etre_annulee(): void
    {
        $vente = $this->caisse()->enregistrer([
            'client_id' => $this->client->id,
            'libelle' => 'Vente de shampoing',
            'montant' => 3000,
            'mode_paiement_id' => $this->especes->id,
        ], $this->receptionniste);

        $annulation = $this->caisse()->annuler($vente, 'Retour du produit', $this->admin);

        $this->assertNull($annulation->rendez_vous_id);
        $this->assertSame(-3000, $annulation->montant);
        $this->assertSame('Vente de shampoing', $annulation->libelle);
        $this->assertSame(0, $annulation->reste_apres);
    }

    public function test_l_annulation_porte_la_date_du_jour(): void
    {
        $paiement = $this->payer($this->rdv(), 8000, null, $this->admin, ['date' => '2026-11-02']);

        $annulation = $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertSame('2026-11-02', $paiement->date->toDateString());
        $this->assertSame('2026-11-09', $annulation->date->toDateString());
    }
}