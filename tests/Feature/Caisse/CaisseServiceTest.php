<?php

namespace Tests\Feature\Caisse;

use App\Enums\StatutRendezVous;
use App\Enums\TypePaiement;
use App\Models\Client;
use App\Models\HistoriqueAction;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Support\Fcfa;
use Illuminate\Auth\Access\AuthorizationException;

class CaisseServiceTest extends CaisseTestCase
{
    public function test_un_paiement_partiel_puis_le_solde(): void
    {
        $rdv = $this->rdv();

        $premier = $this->payer($rdv, 5000);

        $this->assertSame(20000, $premier->montant_total);
        $this->assertSame(15000, $premier->reste_apres);
        $this->assertSame(TypePaiement::Paiement, $premier->type);
        $this->assertSame($this->client->id, $premier->client_id);
        $this->assertSame($this->receptionniste->id, $premier->user_id);
        $this->assertSame('2026-11-09', $premier->date->toDateString());

        $second = $this->payer($rdv, 15000, $this->wave);

        $this->assertSame(0, $second->reste_apres);
        $this->assertSame(20000, $rdv->montantPaye());
        $this->assertSame(0, $rdv->resteAPayer());
        $this->assertSame(['du' => 20000, 'paye' => 20000, 'reste' => 0], $this->caisse()->solde($rdv));
    }

    public function test_un_montant_superieur_au_reste_est_refuse(): void
    {
        $rdv = $this->rdv();
        $this->payer($rdv, 5000);

        $this->assertValidationError('montant', fn () => $this->payer($rdv, 15001));
        $this->assertSame(1, Paiement::count());

        // Le reste exact est accepté.
        $this->payer($rdv, 15000);
        $this->assertSame(0, $rdv->resteAPayer());
    }

    public function test_un_rendez_vous_deja_paye_en_entier_ne_peut_plus_etre_encaisse(): void
    {
        $rdv = $this->rdv();
        $this->payer($rdv, 20000);

        $this->assertValidationError('montant', fn () => $this->payer($rdv, 1));
    }

    public function test_les_montants_invalides_sont_refuses(): void
    {
        $rdv = $this->rdv();

        foreach ([0, -5, 'abc', '5000.5', 5000.5, null, '', 10_000_001] as $montant) {
            $this->assertValidationError('montant', fn () => $this->caisse()->enregistrer([
                'rendez_vous_id' => $rdv->id,
                'montant' => $montant,
                'mode_paiement_id' => $this->especes->id,
            ], $this->receptionniste));
        }

        $this->assertSame(0, Paiement::count());
    }

    public function test_un_montant_en_texte_entier_est_accepte(): void
    {
        $paiement = $this->caisse()->enregistrer([
            'rendez_vous_id' => $this->rdv()->id,
            'montant' => '7500',
            'mode_paiement_id' => $this->especes->id,
        ], $this->receptionniste);

        $this->assertSame(7500, $paiement->montant);
    }

    public function test_le_mode_de_paiement_doit_exister_et_etre_actif(): void
    {
        $rdv = $this->rdv();
        $inactif = ModePaiement::factory()->inactif()->create();

        $this->assertValidationError('mode_paiement_id', fn () => $this->payer($rdv, 1000, $inactif));
        $this->assertValidationError('mode_paiement_id', fn () => $this->caisse()->enregistrer([
            'rendez_vous_id' => $rdv->id, 'montant' => 1000, 'mode_paiement_id' => 9999,
        ], $this->receptionniste));
        $this->assertValidationError('mode_paiement_id', fn () => $this->caisse()->enregistrer([
            'rendez_vous_id' => $rdv->id, 'montant' => 1000,
        ], $this->receptionniste));

        $this->assertSame(0, Paiement::count());
    }

    public function test_seuls_les_rendez_vous_confirmes_ou_plus_avances_sont_encaissables(): void
    {
        foreach ([StatutRendezVous::Confirme, StatutRendezVous::ClientArrive, StatutRendezVous::EnCours, StatutRendezVous::Termine] as $statut) {
            $this->assertSame(1000, $this->payer($this->rdv($statut), 1000)->montant, $statut->value);
        }

        foreach ([StatutRendezVous::EnAttente, StatutRendezVous::Annule, StatutRendezVous::ClientAbsent] as $statut) {
            $rdv = $this->rdv($statut);

            $this->assertValidationError('rendez_vous_id', fn () => $this->payer($rdv, 1000));
        }

        $this->assertSame(4, Paiement::count());
    }

    public function test_un_rendez_vous_sans_prix_ne_peut_pas_etre_encaisse(): void
    {
        $rdv = $this->rdv(StatutRendezVous::Termine, ['prix_prevu' => 0]);

        $this->assertValidationError('montant', fn () => $this->payer($rdv, 1000));
    }

    public function test_un_rendez_vous_inconnu_est_refuse(): void
    {
        $this->assertValidationError('rendez_vous_id', fn () => $this->caisse()->enregistrer([
            'rendez_vous_id' => 9999, 'montant' => 1000, 'mode_paiement_id' => $this->especes->id,
        ], $this->receptionniste));
    }

    public function test_le_client_vient_toujours_du_rendez_vous(): void
    {
        $autre = Client::factory()->create();

        $paiement = $this->payer($this->rdv(), 1000, null, null, ['client_id' => $autre->id, 'libelle' => 'ignoré']);

        $this->assertSame($this->client->id, $paiement->client_id);
        $this->assertNull($paiement->libelle);
    }

    public function test_une_vente_sans_rendez_vous(): void
    {
        $paiement = $this->caisse()->enregistrer([
            'client_id' => $this->client->id,
            'libelle' => '  Vente de shampoing  ',
            'montant' => 3000,
            'mode_paiement_id' => $this->especes->id,
        ], $this->receptionniste);

        $this->assertNull($paiement->rendez_vous_id);
        $this->assertSame('Vente de shampoing', $paiement->libelle);
        $this->assertSame(3000, $paiement->montant_total);
        $this->assertSame(0, $paiement->reste_apres);
    }

    public function test_une_vente_sans_rendez_vous_exige_client_et_libelle(): void
    {
        $base = ['montant' => 3000, 'mode_paiement_id' => $this->especes->id];

        $this->assertValidationError('client_id', fn () => $this->caisse()->enregistrer(
            $base + ['libelle' => 'Produit'],
            $this->receptionniste,
        ));
        $this->assertValidationError('libelle', fn () => $this->caisse()->enregistrer(
            $base + ['client_id' => $this->client->id],
            $this->receptionniste,
        ));
        $this->assertValidationError('libelle', fn () => $this->caisse()->enregistrer(
            $base + ['client_id' => $this->client->id, 'libelle' => '   '],
            $this->receptionniste,
        ));

        $archive = Client::factory()->inactif()->create();
        $this->assertValidationError('client_id', fn () => $this->caisse()->enregistrer(
            $base + ['client_id' => $archive->id, 'libelle' => 'Produit'],
            $this->receptionniste,
        ));

        $this->assertSame(0, Paiement::count());
    }

    public function test_la_reference_est_nettoyee_et_limitee(): void
    {
        $rdv = $this->rdv();

        $this->assertSame('WV123', $this->payer($rdv, 1000, null, null, ['reference' => '  WV123 '])->reference);
        $this->assertNull($this->payer($rdv, 1000, null, null, ['reference' => '   '])->reference);
        $this->assertValidationError('reference', fn () => $this->payer($rdv, 1000, null, null, ['reference' => str_repeat('a', 101)]));
    }

    public function test_la_date_par_defaut_est_aujourd_hui(): void
    {
        $this->assertSame('2026-11-09', $this->payer($this->rdv(), 1000)->date->toDateString());
    }

    public function test_une_date_future_ou_invalide_est_refusee(): void
    {
        $rdv = $this->rdv();

        $this->assertValidationError('date', fn () => $this->payer($rdv, 1000, null, $this->admin, ['date' => '2026-11-10']));
        $this->assertValidationError('date', fn () => $this->payer($rdv, 1000, null, $this->admin, ['date' => '2026-02-31']));
        $this->assertValidationError('date', fn () => $this->payer($rdv, 1000, null, $this->admin, ['date' => 'hier']));
    }

    public function test_une_autre_date_demande_la_permission_de_correction(): void
    {
        $rdv = $this->rdv();

        $this->assertValidationError('date', fn () => $this->payer($rdv, 1000, null, $this->receptionniste, ['date' => '2026-11-02']));

        $paiement = $this->payer($rdv, 1000, null, $this->admin, ['date' => '2026-11-02']);
        $this->assertSame('2026-11-02', $paiement->date->toDateString());

        // Aujourd'hui, saisi explicitement, reste permis à tous.
        $this->assertSame('2026-11-09', $this->payer($rdv, 1000, null, $this->receptionniste, ['date' => '2026-11-09'])->date->toDateString());
    }

    public function test_le_numero_de_recu(): void
    {
        $paiement = $this->payer($this->rdv(), 1000);

        $this->assertSame('R-2026-'.str_pad((string) $paiement->id, 6, '0', STR_PAD_LEFT), $paiement->numero());
        $this->assertMatchesRegularExpression('/^R-\d{4}-\d{6}$/', $paiement->numero());
    }

    public function test_le_professionnel_ne_peut_pas_encaisser(): void
    {
        $professionnel = \App\Models\User::factory()->professionnel()->create();

        $this->expectException(AuthorizationException::class);

        $this->payer($this->rdv(), 1000, null, $professionnel);
    }

    public function test_un_compte_desactive_ne_peut_pas_encaisser(): void
    {
        $inactif = \App\Models\User::factory()->administratrice()->inactif()->create();

        $this->expectException(AuthorizationException::class);

        $this->payer($this->rdv(), 1000, null, $inactif);
    }

    public function test_l_encaissement_est_journalise_sans_donnee_personnelle(): void
    {
        $this->client->update(['prenom' => 'Qwerty', 'nom' => 'Zzyzx']);
        $rdv = $this->rdv();

        $paiement = $this->payer($rdv, 5000, $this->wave);

        $log = HistoriqueAction::where('action', 'paiement.enregistre')->firstOrFail();

        $this->assertSame($this->receptionniste->id, $log->user_id);
        $this->assertSame($paiement->id, $log->sujet_id);
        $this->assertSame(5000, $log->nouvelles_valeurs['montant']);
        $this->assertSame($rdv->id, $log->nouvelles_valeurs['rendez_vous_id']);

        $contenu = json_encode([$log->description, $log->nouvelles_valeurs]);
        $this->assertStringNotContainsString('Zzyzx', $contenu);
        $this->assertStringNotContainsString('Qwerty', $contenu);
    }

    public function test_une_ligne_de_caisse_ne_se_modifie_ni_ne_se_supprime(): void
    {
        $paiement = $this->payer($this->rdv(), 5000);

        $this->assertFalse($paiement->update(['montant' => 1]));
        $this->assertFalse($paiement->delete());

        $this->assertSame(5000, Paiement::find($paiement->id)->montant);
        $this->assertSame(1, Paiement::count());
    }

    public function test_le_format_des_montants(): void
    {
        $this->assertSame(Fcfa::format(5000), $this->payer($this->rdv(), 5000)->montantFormate());
    }
}