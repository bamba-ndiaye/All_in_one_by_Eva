<?php

namespace Tests\Feature\Agenda;

use App\Enums\StatutRendezVous;
use App\Models\Employe;
use App\Models\Forfait;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentScheduler;
use Illuminate\Support\Carbon;

class AppointmentRulesTest extends AgendaTestCase
{
    private function erreurs(array $override = [], ?User $user = null, ?RendezVous $rdv = null): array
    {
        return app(AppointmentScheduler::class)->erreurs($this->input($override), $user ?? $this->admin, $rdv);
    }

    private function rdvExistant(array $override = []): RendezVous
    {
        return RendezVous::factory()->create(array_merge([
            'employe_id' => $this->employe->id,
            'client_id' => $this->client->id,
            'date' => '2026-11-09',
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
        ], $override));
    }

    private function forfaitCoupeMassage(array $override = []): Forfait
    {
        $forfait = Forfait::factory()->create(array_merge(['prix' => 10000], $override));
        $forfait->services()->sync([$this->coupe->id, $this->massage->id]);

        return $forfait;
    }

    public function test_une_reservation_valide_n_a_aucune_erreur(): void
    {
        $this->assertSame([], $this->erreurs());
    }

    public function test_un_client_archive_ou_un_employe_desactive_est_refuse(): void
    {
        $this->client->update(['is_active' => false]);
        $this->assertArrayHasKey('client_id', $this->erreurs());

        $this->client->update(['is_active' => true]);
        $this->employe->update(['is_active' => false]);
        $this->assertArrayHasKey('employe_id', $this->erreurs());
    }

    public function test_il_faut_au_moins_une_prestation(): void
    {
        $this->assertArrayHasKey('services', $this->erreurs(['services' => [], 'forfaits' => []]));
    }

    public function test_une_prestation_ou_un_forfait_desactive_est_refuse(): void
    {
        $this->coupe->update(['is_active' => false]);
        $this->assertArrayHasKey('services', $this->erreurs());

        $this->coupe->update(['is_active' => true]);
        $forfait = $this->forfaitCoupeMassage(['is_active' => false]);

        $this->assertArrayHasKey('forfaits', $this->erreurs(['services' => [], 'forfaits' => [$forfait->id], 'zone' => 'femmes']));
    }

    public function test_la_personne_doit_proposer_toutes_les_prestations(): void
    {
        $autre = Service::factory()->create(['nom' => 'Soin inconnu', 'duree_minutes' => 30]);

        $erreurs = $this->erreurs(['services' => [$this->coupe->id, $autre->id]]);

        $this->assertArrayHasKey('employe_id', $erreurs);
        $this->assertStringContainsString('Soin inconnu', $erreurs['employe_id']);
    }

    public function test_une_prestation_reservee_aux_femmes_exige_l_espace_femmes(): void
    {
        $this->assertArrayHasKey('zone', $this->erreurs(['services' => [$this->massage->id], 'zone' => 'mixte']));
        $this->assertSame([], $this->erreurs(['services' => [$this->massage->id], 'zone' => 'femmes']));
    }

    public function test_une_prestation_ne_peut_pas_etre_comptee_deux_fois(): void
    {
        $forfait = $this->forfaitCoupeMassage();

        $erreurs = $this->erreurs([
            'services' => [$this->coupe->id],
            'forfaits' => [$forfait->id],
            'zone' => 'femmes',
        ]);

        $this->assertArrayHasKey('services', $erreurs);
        $this->assertStringContainsString('Coupe test', $erreurs['services']);
    }

    public function test_un_jour_sans_horaires_est_refuse(): void
    {
        // Mardi 10 novembre : aucune plage.
        $this->assertArrayHasKey('date', $this->erreurs(['date' => '2026-11-10']));
    }

    public function test_le_rendez_vous_doit_tenir_dans_une_plage_de_travail(): void
    {
        // Coupe de 45 minutes ; plages 09:00-13:00 et 14:00-18:00.
        $cas = [
            '08:30' => false,   // avant l'ouverture
            '09:00' => true,
            '12:15' => true,    // finit pile à 13:00
            '12:30' => false,   // finirait à 13:15, après la pause
            '13:15' => false,   // pendant la pause
            '14:00' => true,
            '17:15' => true,    // finit pile à 18:00
            '17:30' => false,   // dépasserait 18:00
        ];

        foreach ($cas as $heure => $valide) {
            $erreurs = $this->erreurs(['heure_debut' => $heure]);

            $this->assertSame($valide, $erreurs === [], "Début à {$heure} : ".json_encode($erreurs));
        }
    }

    public function test_un_rendez_vous_dans_le_passe_demande_une_permission(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 11:00:00'));

        $this->assertArrayHasKey('date', $this->erreurs([], $this->receptionniste));
        $this->assertArrayHasKey('date', $this->erreurs(['date' => '2026-11-02'], $this->receptionniste));

        $this->assertSame([], $this->erreurs([], $this->admin));

        $this->receptionniste->grantPermission('appointments.create_past');
        $this->assertSame([], $this->erreurs([], $this->receptionniste));
    }

    public function test_deux_rendez_vous_ne_peuvent_pas_se_chevaucher(): void
    {
        $this->rdvExistant();   // 10:00 - 11:00

        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '10:30']));
        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '10:00']));
        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '09:30']));   // finit à 10:15
    }

    public function test_un_rendez_vous_peut_commencer_quand_l_autre_finit(): void
    {
        $this->rdvExistant();   // 10:00 - 11:00

        $this->assertSame([], $this->erreurs(['heure_debut' => '11:00']));
        $this->assertSame([], $this->erreurs(['heure_debut' => '09:15']));   // finit pile à 10:00
    }

    public function test_la_pause_entre_rendez_vous_est_respectee(): void
    {
        config(['salon.rendez_vous.pause_minutes' => 15]);
        $this->rdvExistant();   // 10:00 - 11:00

        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '11:00']));
        $this->assertSame([], $this->erreurs(['heure_debut' => '11:15']));
        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '09:15']));
        $this->assertSame([], $this->erreurs(['heure_debut' => '09:00']));   // finit à 09:45, +15 = 10:00
    }

    public function test_un_rendez_vous_annule_ou_absent_libere_le_creneau(): void
    {
        $annule = $this->rdvExistant(['statut' => StatutRendezVous::Annule]);
        $this->assertSame([], $this->erreurs(['heure_debut' => '10:30']));

        $annule->update(['statut' => StatutRendezVous::ClientAbsent]);
        $this->assertSame([], $this->erreurs(['heure_debut' => '10:30']));

        $annule->update(['statut' => StatutRendezVous::Confirme]);
        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '10:30']));
    }

    public function test_un_autre_employe_n_est_pas_bloque(): void
    {
        $this->rdvExistant();

        $autre = Employe::factory()->create();
        $autre->services()->sync([$this->coupe->id]);
        $autre->disponibilites()->create(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '18:00']);

        $this->assertSame([], $this->erreurs(['employe_id' => $autre->id, 'heure_debut' => '10:30']));
    }

    public function test_un_rendez_vous_ne_se_bloque_pas_lui_meme_quand_on_le_deplace(): void
    {
        $rdv = $this->rdvExistant();   // 10:00 - 11:00

        $this->assertSame([], $this->erreurs(['heure_debut' => '10:15'], $this->admin, $rdv));
    }

    public function test_un_rendez_vous_inchange_n_est_pas_revalide(): void
    {
        // Rendez-vous créé avant un changement d'horaires : il est hors plage, mais on ne le déplace pas.
        $rdv = $this->rdvExistant(['heure_debut' => '06:00:00', 'heure_fin' => '06:45:00']);

        $this->assertSame([], $this->erreurs(['heure_debut' => '06:00'], $this->receptionniste, $rdv));
        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '06:15'], $this->receptionniste, $rdv));
    }

    public function test_les_formats_invalides_sont_refuses(): void
    {
        $this->assertArrayHasKey('date', $this->erreurs(['date' => 'pas-une-date']));
        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => '25:00']));
        $this->assertArrayHasKey('heure_debut', $this->erreurs(['heure_debut' => 'abc']));
    }

    public function test_la_composition_fige_prix_et_durees_et_repartit_le_forfait(): void
    {
        $forfait = $this->forfaitCoupeMassage(['prix' => 10000]);

        $compo = app(AppointmentScheduler::class)->composer([], [$forfait->id]);

        $this->assertCount(2, $compo['lignes']);
        $this->assertSame(10000, $compo['prix']);
        $this->assertSame(105, $compo['duree']);
        $this->assertSame([], $compo['doublons']);

        foreach ($compo['lignes'] as $ligne) {
            $this->assertSame($forfait->id, $ligne['forfait_id']);
        }

        // Coupe 5 000 + massage 15 000 = 20 000 : le forfait à 10 000 est réparti au prorata.
        $parts = array_column($compo['lignes'], 'prix_applique', 'service_id');
        $this->assertSame(2500, $parts[$this->coupe->id]);
        $this->assertSame(7500, $parts[$this->massage->id]);
    }

    public function test_une_prestation_seule_et_un_forfait_s_additionnent(): void
    {
        $autre = Service::factory()->create(['prix' => 3000, 'duree_minutes' => 30]);
        $forfait = $this->forfaitCoupeMassage(['prix' => 10000]);

        $compo = app(AppointmentScheduler::class)->composer([$autre->id], [$forfait->id]);

        $this->assertSame(13000, $compo['prix']);
        $this->assertSame(135, $compo['duree']);
    }

    public function test_les_creneaux_libres_respectent_horaires_et_pause_dejeuner(): void
    {
        $libres = app(AppointmentScheduler::class)
            ->creneauxLibres($this->employe, Carbon::parse('2026-11-09'), 60);

        foreach (['09:00', '09:15', '12:00', '14:00', '17:00'] as $heure) {
            $this->assertContains($heure, $libres, $heure);
        }

        foreach (['08:45', '12:15', '13:00', '13:45', '17:15'] as $heure) {
            $this->assertNotContains($heure, $libres, $heure);
        }
    }

    public function test_les_creneaux_libres_excluent_les_rendez_vous_existants(): void
    {
        $this->rdvExistant();   // 10:00 - 11:00

        $libres = app(AppointmentScheduler::class)
            ->creneauxLibres($this->employe, Carbon::parse('2026-11-09'), 60);

        $this->assertContains('09:00', $libres);   // finit pile à 10:00
        $this->assertContains('11:00', $libres);

        foreach (['09:15', '10:00', '10:30', '10:45'] as $heure) {
            $this->assertNotContains($heure, $libres, $heure);
        }
    }

    public function test_les_creneaux_libres_excluent_le_passe_sauf_demande(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 11:00:00'));
        $scheduler = app(AppointmentScheduler::class);

        $libres = $scheduler->creneauxLibres($this->employe, Carbon::parse('2026-11-09'), 60);
        $this->assertNotContains('09:00', $libres);
        $this->assertNotContains('10:45', $libres);
        $this->assertContains('11:00', $libres);

        $tous = $scheduler->creneauxLibres($this->employe, Carbon::parse('2026-11-09'), 60, null, 15, true);
        $this->assertContains('09:00', $tous);
    }

    public function test_un_jour_sans_horaires_n_a_aucun_creneau(): void
    {
        $this->assertSame([], app(AppointmentScheduler::class)
            ->creneauxLibres($this->employe, Carbon::parse('2026-11-10'), 60));
    }
}