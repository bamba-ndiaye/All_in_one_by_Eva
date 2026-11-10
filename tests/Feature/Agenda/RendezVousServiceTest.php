<?php

namespace Tests\Feature\Agenda;

use App\Enums\StatutRendezVous;
use App\Models\Client;
use App\Models\Forfait;
use App\Models\HistoriqueAction;
use App\Models\RendezVous;
use App\Services\RendezVousService;

class RendezVousServiceTest extends AgendaTestCase
{
    private function service(): RendezVousService
    {
        return app(RendezVousService::class);
    }

    private function creer(array $override = [], $user = null): RendezVous
    {
        return $this->service()->creer($this->input($override), $user ?? $this->receptionniste);
    }

    public function test_la_reservation_enregistre_lignes_prix_et_heure_de_fin(): void
    {
        $rdv = $this->creer(['services' => [$this->coupe->id, $this->massage->id], 'zone' => 'femmes'])->fresh();

        $this->assertSame('10:00:00', $rdv->heure_debut);
        $this->assertSame('11:45:00', $rdv->heure_fin);
        $this->assertSame(20000, $rdv->prix_prevu);
        $this->assertSame(StatutRendezVous::Confirme, $rdv->statut);
        $this->assertSame($this->receptionniste->id, $rdv->created_by);
        $this->assertSame(105, $rdv->dureeMinutes());

        $this->assertCount(2, $rdv->lignes);
        $coupe = $rdv->lignes->firstWhere('service_id', $this->coupe->id);
        $this->assertSame('Coupe test', $coupe->nom);
        $this->assertSame(5000, $coupe->prix_applique);
        $this->assertSame(45, $coupe->duree_appliquee);
    }

    public function test_un_forfait_est_enregistre_avec_son_prix_propre(): void
    {
        $forfait = Forfait::factory()->create(['prix' => 10000]);
        $forfait->services()->sync([$this->coupe->id, $this->massage->id]);

        $rdv = $this->creer(['services' => [], 'forfaits' => [$forfait->id], 'zone' => 'femmes'])->fresh();

        $this->assertSame(10000, $rdv->prix_prevu);
        $this->assertSame(10000, $rdv->lignes->sum('prix_applique'));
        $this->assertSame(105, $rdv->dureeMinutes());
        $this->assertTrue($rdv->lignes->every(fn ($ligne) => $ligne->forfait_id === $forfait->id));
    }

    public function test_les_prix_et_durees_sont_figes_a_la_reservation(): void
    {
        $rdv = $this->creer();

        $this->coupe->update(['prix' => 9999, 'duree_minutes' => 90]);

        $rdv = $rdv->fresh();
        $this->assertSame(5000, $rdv->prix_prevu);
        $this->assertSame(5000, $rdv->lignes->first()->prix_applique);
        $this->assertSame('10:45:00', $rdv->heure_fin);

        $nouveau = $this->creer(['heure_debut' => '14:00']);
        $this->assertSame(9999, $nouveau->prix_prevu);
        $this->assertSame('15:30:00', $nouveau->fresh()->heure_fin);
    }

    public function test_le_statut_initial_peut_etre_en_attente(): void
    {
        $this->assertSame(StatutRendezVous::EnAttente, $this->creer(['statut' => 'en_attente'])->statut);
    }

    public function test_un_statut_initial_autre_que_en_attente_ou_confirme_est_refuse(): void
    {
        $this->assertValidationError('statut', fn () => $this->creer(['statut' => 'termine']));
        $this->assertSame(0, RendezVous::count());
    }

    public function test_une_double_reservation_est_refusee_et_ne_laisse_rien_en_base(): void
    {
        $this->creer();

        $this->assertValidationError('heure_debut', fn () => $this->creer(['heure_debut' => '10:30']));

        $this->assertSame(1, RendezVous::count());
        $this->assertSame(1, $this->employe->fresh()->services()->count() > 0 ? RendezVous::first()->lignes()->count() : 0);
    }

    public function test_la_creation_est_journalisee_sans_donnee_personnelle(): void
    {
        $client = Client::factory()->create(['prenom' => 'Qwerty', 'nom' => 'Zzyzx']);

        $rdv = $this->creer(['client_id' => $client->id]);

        $log = HistoriqueAction::where('action', 'rendezvous.cree')->firstOrFail();

        $this->assertSame($this->receptionniste->id, $log->user_id);
        $this->assertSame($rdv->id, $log->sujet_id);

        $contenu = json_encode([$log->description, $log->nouvelles_valeurs]);
        $this->assertStringNotContainsString('Zzyzx', $contenu);
        $this->assertStringNotContainsString('Qwerty', $contenu);
    }

    public function test_deplacer_un_rendez_vous_est_journalise(): void
    {
        $rdv = $this->creer();

        $this->service()->modifier($rdv, $this->input(['date' => '2026-11-16', 'heure_debut' => '15:00']), $this->receptionniste);

        $rdv = $rdv->fresh();
        $this->assertSame('2026-11-16', $rdv->date->toDateString());
        $this->assertSame('15:00:00', $rdv->heure_debut);
        $this->assertSame('15:45:00', $rdv->heure_fin);

        $log = HistoriqueAction::where('action', 'rendezvous.modifie')->firstOrFail();
        $this->assertSame('2026-11-09', $log->anciennes_valeurs['date']);
        $this->assertSame('2026-11-16', $log->nouvelles_valeurs['date']);
        $this->assertSame('10:00', $log->anciennes_valeurs['heure_debut']);
    }

    public function test_changer_les_prestations_met_a_jour_lignes_prix_et_fin(): void
    {
        $rdv = $this->creer();

        $this->service()->modifier(
            $rdv,
            $this->input(['services' => [$this->coupe->id, $this->massage->id], 'zone' => 'femmes']),
            $this->receptionniste,
        );

        $rdv = $rdv->fresh();
        $this->assertCount(2, $rdv->lignes);
        $this->assertSame(20000, $rdv->prix_prevu);
        $this->assertSame('11:45:00', $rdv->heure_fin);
    }

    public function test_deplacer_sur_un_creneau_occupe_est_refuse_sans_rien_changer(): void
    {
        $this->creer();                                   // 10:00 - 10:45
        $b = $this->creer(['heure_debut' => '11:00']);    // 11:00 - 11:45

        $this->assertValidationError('heure_debut', fn () => $this->service()->modifier(
            $b,
            $this->input(['heure_debut' => '10:30']),
            $this->receptionniste,
        ));

        $this->assertSame('11:00:00', $b->fresh()->heure_debut);
        $this->assertSame(0, HistoriqueAction::where('action', 'rendezvous.modifie')->count());
    }

    public function test_une_modification_sans_changement_n_ecrit_rien_dans_le_journal(): void
    {
        $rdv = $this->creer();

        $this->service()->modifier($rdv, $this->input(), $this->receptionniste);

        $this->assertSame(0, HistoriqueAction::where('action', 'rendezvous.modifie')->count());
    }

    public function test_modifier_le_commentaire_seul_est_journalise_sans_son_contenu(): void
    {
        $rdv = $this->creer();

        $this->service()->modifier($rdv, $this->input(['commentaire' => 'Allergie au henné']), $this->receptionniste);

        $this->assertSame('Allergie au henné', $rdv->fresh()->commentaire);

        $log = HistoriqueAction::where('action', 'rendezvous.modifie')->firstOrFail();
        $this->assertTrue($log->nouvelles_valeurs['commentaire_modifie']);
        $this->assertStringNotContainsString('henné', json_encode($log->nouvelles_valeurs));
    }

    public function test_un_rendez_vous_termine_ne_se_modifie_plus(): void
    {
        $rdv = $this->creer();
        $rdv->update(['statut' => StatutRendezVous::Termine]);

        $this->assertValidationError('statut', fn () => $this->service()->modifier(
            $rdv,
            $this->input(['heure_debut' => '14:00']),
            $this->admin,
        ));

        $this->assertSame('10:00:00', $rdv->fresh()->heure_debut);
    }

    public function test_le_cycle_normal_des_statuts(): void
    {
        $rdv = $this->creer();

        foreach ([StatutRendezVous::ClientArrive, StatutRendezVous::EnCours, StatutRendezVous::Termine] as $statut) {
            $rdv = $this->service()->changerStatut($rdv, $statut, $this->receptionniste);
            $this->assertSame($statut, $rdv->fresh()->statut);
        }

        $this->assertSame(3, HistoriqueAction::where('action', 'rendezvous.statut')->count());
    }

    public function test_un_passage_de_statut_interdit_est_refuse(): void
    {
        $rdv = $this->creer();
        $rdv = $this->service()->changerStatut($rdv, StatutRendezVous::ClientArrive, $this->admin);
        $rdv = $this->service()->changerStatut($rdv, StatutRendezVous::EnCours, $this->admin);
        $rdv = $this->service()->changerStatut($rdv, StatutRendezVous::Termine, $this->admin);

        // Même l'administratrice ne peut pas rouvrir un rendez-vous terminé.
        $this->assertValidationError('statut', fn () => $this->service()->changerStatut($rdv, StatutRendezVous::Confirme, $this->admin));
        $this->assertSame(StatutRendezVous::Termine, $rdv->fresh()->statut);
    }

    public function test_le_journal_garde_l_ancien_et_le_nouveau_statut(): void
    {
        $rdv = $this->creer();

        $this->service()->changerStatut($rdv, StatutRendezVous::ClientArrive, $this->receptionniste);

        $log = HistoriqueAction::where('action', 'rendezvous.statut')->firstOrFail();
        $this->assertSame(['statut' => 'confirme'], $log->anciennes_valeurs);
        $this->assertSame(['statut' => 'client_arrive'], $log->nouvelles_valeurs);
    }

    public function test_l_annulation_garde_le_motif_et_libere_le_creneau(): void
    {
        $rdv = $this->creer();

        $this->service()->changerStatut($rdv, StatutRendezVous::Annule, $this->receptionniste, '  Cliente malade  ');

        $rdv = $rdv->fresh();
        $this->assertSame(StatutRendezVous::Annule, $rdv->statut);
        $this->assertSame('Cliente malade', $rdv->motif_annulation);

        // Le créneau est de nouveau disponible.
        $this->assertSame(StatutRendezVous::Confirme, $this->creer()->statut);
    }

    public function test_un_client_absent_libere_le_creneau(): void
    {
        $rdv = $this->creer();

        $this->service()->changerStatut($rdv, StatutRendezVous::ClientAbsent, $this->receptionniste);

        $this->assertSame(StatutRendezVous::Confirme, $this->creer(['heure_debut' => '10:15'])->statut);
    }

    public function test_un_rendez_vous_futur_ne_peut_pas_etre_marque_arrive_mais_peut_etre_annule(): void
    {
        $futur = $this->creer(['date' => '2026-11-16']);

        $this->assertValidationError('statut', fn () => $this->service()->changerStatut(
            $futur,
            StatutRendezVous::ClientArrive,
            $this->receptionniste,
        ));
        $this->assertValidationError('statut', fn () => $this->service()->changerStatut(
            $futur,
            StatutRendezVous::ClientAbsent,
            $this->receptionniste,
        ));

        $this->service()->changerStatut($futur, StatutRendezVous::Annule, $this->receptionniste);
        $this->assertSame(StatutRendezVous::Annule, $futur->fresh()->statut);
    }

    public function test_confirmer_un_rendez_vous_en_attente(): void
    {
        $rdv = $this->creer(['statut' => 'en_attente']);

        $this->service()->changerStatut($rdv, StatutRendezVous::Confirme, $this->receptionniste);

        $this->assertSame(StatutRendezVous::Confirme, $rdv->fresh()->statut);
    }
}