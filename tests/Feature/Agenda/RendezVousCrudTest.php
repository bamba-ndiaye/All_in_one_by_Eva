<?php

namespace Tests\Feature\Agenda;

use App\Enums\StatutRendezVous;
use App\Models\Client;
use App\Models\Employe;
use App\Models\HistoriqueAction;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\RendezVousService;
use App\Support\Fcfa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

class RendezVousCrudTest extends AgendaTestCase
{
    private Client $zorro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zorro = Client::factory()->create(['prenom' => 'Aminata', 'nom' => 'Zorro', 'telephone' => '+221771234567']);
    }

    private function donnees(array $override = []): array
    {
        return array_merge($this->input(['client_id' => $this->zorro->id]), [
            'statut' => 'confirme',
            'commentaire' => '',
        ], $override);
    }

    private function existant(array $override = []): RendezVous
    {
        return app(RendezVousService::class)->creer(
            $this->input(array_merge(['client_id' => $this->zorro->id], $override)),
            $this->admin,
        );
    }

    /** @return array{User, Employe} Un professionnel lié à une fiche employé qui propose la coupe. */
    private function professionnel(): array
    {
        $user = User::factory()->professionnel()->create();
        $employe = Employe::factory()->create(['user_id' => $user->id]);
        $employe->services()->sync([$this->coupe->id]);
        $employe->disponibilites()->create(['jour_semaine' => 1, 'heure_debut' => '09:00', 'heure_fin' => '18:00']);

        return [$user, $employe];
    }

    // --- Formulaire ----------------------------------------------------------------

    public function test_le_formulaire_de_creation_est_reserve_au_personnel_qui_gere_les_rendez_vous(): void
    {
        $this->actingAs($this->receptionniste)->get(route('rendez-vous.create'))->assertOk()->assertSee('Nouveau rendez-vous');
        $this->actingAs($this->admin)->get(route('rendez-vous.create'))->assertOk();
        $this->actingAs(User::factory()->professionnel()->create())->get(route('rendez-vous.create'))->assertForbidden();
    }

    public function test_le_formulaire_preselectionne_le_client_la_date_et_la_personne(): void
    {
        $this->actingAs($this->receptionniste)
            ->get(route('rendez-vous.create', ['client' => $this->zorro->id, 'date' => '2026-11-16', 'employe' => $this->employe->id]))
            ->assertOk()
            ->assertSee('Aminata Zorro')
            ->assertSee('2026-11-16');
    }

    public function test_le_formulaire_propose_les_prestations_actives_seulement(): void
    {
        $this->massage->update(['is_active' => false]);

        $this->actingAs($this->receptionniste)
            ->get(route('rendez-vous.create'))
            ->assertSee('Coupe test')
            ->assertDontSee('Massage test');
    }

    // --- Création ------------------------------------------------------------------

    public function test_la_receptionniste_cree_un_rendez_vous(): void
    {
        $this->actingAs($this->receptionniste)
            ->post(route('rendez-vous.store'), $this->donnees())
            ->assertRedirect();

        $rdv = RendezVous::firstOrFail();

        $this->assertSame($this->zorro->id, $rdv->client_id);
        $this->assertSame('10:45:00', $rdv->heure_fin);
        $this->assertSame(5000, $rdv->prix_prevu);
        $this->assertSame(StatutRendezVous::Confirme, $rdv->statut);
        $this->assertSame($this->receptionniste->id, $rdv->created_by);
        $this->assertCount(1, $rdv->lignes);
    }

    public function test_la_creation_redirige_vers_la_fiche_du_rendez_vous(): void
    {
        $reponse = $this->actingAs($this->receptionniste)->post(route('rendez-vous.store'), $this->donnees());

        $reponse->assertRedirect(route('rendez-vous.show', RendezVous::firstOrFail()));
        $reponse->assertSessionHas('success');
    }

    public function test_le_statut_initial_peut_etre_en_attente(): void
    {
        $this->actingAs($this->receptionniste)
            ->post(route('rendez-vous.store'), $this->donnees(['statut' => 'en_attente']));

        $this->assertSame(StatutRendezVous::EnAttente, RendezVous::firstOrFail()->statut);
    }

    public function test_un_statut_initial_interdit_est_refuse(): void
    {
        $this->actingAs($this->receptionniste)
            ->post(route('rendez-vous.store'), $this->donnees(['statut' => 'termine']))
            ->assertSessionHasErrors('statut');

        $this->assertSame(0, RendezVous::count());
    }

    public function test_une_double_reservation_est_refusee(): void
    {
        $this->existant();   // 10:00 - 10:45

        $this->actingAs($this->receptionniste)
            ->post(route('rendez-vous.store'), $this->donnees(['heure_debut' => '10:30']))
            ->assertSessionHasErrors('heure_debut');

        $this->assertSame(1, RendezVous::count());
    }

    public function test_un_rendez_vous_dans_le_passe_demande_une_permission(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 11:00:00'));

        $this->actingAs($this->receptionniste)
            ->post(route('rendez-vous.store'), $this->donnees())
            ->assertSessionHasErrors('date');
        $this->assertSame(0, RendezVous::count());

        $this->actingAs($this->admin)
            ->post(route('rendez-vous.store'), $this->donnees())
            ->assertSessionHasNoErrors();
        $this->assertSame(1, RendezVous::count());
    }

    public function test_les_champs_invalides_sont_refuses(): void
    {
        $this->actingAs($this->receptionniste);

        $cas = [
            'client_id' => ['', '9999', 'abc'],
            'employe_id' => ['', '9999'],
            'date' => ['', '2026-02-31', 'demain', '09/11/2026'],
            'heure_debut' => ['', '25:00', 'abc', '10h'],
            'zone' => ['', 'dehors'],
            'commentaire' => [str_repeat('a', 1001)],
        ];

        foreach ($cas as $champ => $valeurs) {
            foreach ($valeurs as $valeur) {
                $this->post(route('rendez-vous.store'), $this->donnees([$champ => $valeur]))
                    ->assertSessionHasErrors($champ);
            }
        }

        $this->post(route('rendez-vous.store'), $this->donnees(['services' => [], 'forfaits' => []]))
            ->assertSessionHasErrors('services');

        $this->assertSame(0, RendezVous::count());
    }

    public function test_le_professionnel_ne_peut_pas_creer(): void
    {
        $this->actingAs(User::factory()->professionnel()->create())
            ->post(route('rendez-vous.store'), $this->donnees())
            ->assertForbidden();

        $this->assertSame(0, RendezVous::count());
    }

    // --- Créneaux et clients (JSON) -----------------------------------------------

    public function test_les_creneaux_libres_sont_calcules_par_le_serveur(): void
    {
        $this->existant();   // 10:00 - 10:45

        $reponse = $this->actingAs($this->receptionniste)->getJson(route('rendez-vous.creneaux', [
            'employe' => $this->employe->id,
            'date' => '2026-11-09',
            'services' => [$this->coupe->id],
        ]));

        $reponse->assertOk()->assertJsonPath('duree', 45);

        $creneaux = $reponse->json('creneaux');

        $this->assertContains('09:00', $creneaux);
        $this->assertContains('10:45', $creneaux);
        $this->assertNotContains('10:00', $creneaux);
        $this->assertNotContains('13:00', $creneaux);   // pause déjeuner
    }

    public function test_les_creneaux_ignorent_le_rendez_vous_en_cours_de_modification(): void
    {
        $rdv = $this->existant();

        $creneaux = $this->actingAs($this->receptionniste)->getJson(route('rendez-vous.creneaux', [
            'employe' => $this->employe->id,
            'date' => '2026-11-09',
            'services' => [$this->coupe->id],
            'ignore' => $rdv->id,
        ]))->json('creneaux');

        $this->assertContains('10:00', $creneaux);
    }

    public function test_les_creneaux_sont_vides_sans_prestation(): void
    {
        $this->actingAs($this->receptionniste)
            ->getJson(route('rendez-vous.creneaux', ['employe' => $this->employe->id, 'date' => '2026-11-09']))
            ->assertOk()
            ->assertJsonPath('creneaux', []);
    }

    public function test_les_creneaux_refusent_les_parametres_invalides_et_le_professionnel(): void
    {
        $this->actingAs($this->receptionniste);

        $this->getJson(route('rendez-vous.creneaux', ['date' => '2026-11-09']))->assertStatus(422);
        $this->getJson(route('rendez-vous.creneaux', ['employe' => 9999, 'date' => '2026-11-09']))->assertStatus(422);
        $this->getJson(route('rendez-vous.creneaux', ['employe' => $this->employe->id, 'date' => '2026-02-31']))->assertStatus(422);

        $this->actingAs(User::factory()->professionnel()->create())
            ->getJson(route('rendez-vous.creneaux', ['employe' => $this->employe->id, 'date' => '2026-11-09']))
            ->assertForbidden();
    }

    public function test_la_recherche_de_clients(): void
    {
        Client::factory()->inactif()->create(['prenom' => 'Zorra', 'nom' => 'Archivee']);
        $this->actingAs($this->receptionniste);

        $this->getJson(route('rendez-vous.clients', ['q' => 'Zor']))
            ->assertOk()
            ->assertJsonCount(1, 'clients')
            ->assertJsonPath('clients.0.nom', 'Aminata Zorro')
            ->assertJsonPath('clients.0.telephone', '+221 77 123 45 67');

        $this->getJson(route('rendez-vous.clients', ['q' => '77123']))->assertJsonCount(1, 'clients');
        $this->getJson(route('rendez-vous.clients', ['q' => 'Z']))->assertJsonCount(0, 'clients');
        $this->getJson(route('rendez-vous.clients', ['q' => ['x']]))->assertJsonCount(0, 'clients');
    }

    public function test_la_recherche_de_clients_est_reservee(): void
    {
        $this->actingAs(User::factory()->professionnel()->create())
            ->getJson(route('rendez-vous.clients', ['q' => 'Zor']))
            ->assertForbidden();
    }

    // --- Fiche ---------------------------------------------------------------------

    public function test_la_fiche_affiche_prestations_prix_et_historique(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->receptionniste)
            ->get(route('rendez-vous.show', $rdv))
            ->assertOk()
            ->assertSee('Aminata Zorro')
            ->assertSee('Coupe test')
            ->assertSee(Fcfa::format(5000))
            ->assertSee('Historique des modifications')
            ->assertSee('Modifier ou déplacer');
    }

    public function test_le_professionnel_ne_voit_que_ses_fiches_et_pas_l_historique(): void
    {
        [$pro, $monEmploye] = $this->professionnel();

        $mien = $this->existant(['employe_id' => $monEmploye->id]);
        $autre = $this->existant(['heure_debut' => '14:00']);

        $this->actingAs($pro);

        $this->get(route('rendez-vous.show', $mien))
            ->assertOk()
            ->assertSee('Coupe test')
            ->assertDontSee('Historique des modifications')
            ->assertDontSee('Modifier ou déplacer');

        $this->get(route('rendez-vous.show', $autre))->assertForbidden();
    }

    // --- Statuts -------------------------------------------------------------------

    public function test_la_receptionniste_change_le_statut(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->receptionniste)
            ->patch(route('rendez-vous.statut', $rdv), ['statut' => 'client_arrive'])
            ->assertSessionHas('success');

        $this->assertSame(StatutRendezVous::ClientArrive, $rdv->fresh()->statut);
    }

    public function test_un_passage_interdit_est_refuse_meme_pour_l_administratrice(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->admin)
            ->patch(route('rendez-vous.statut', $rdv), ['statut' => 'termine'])
            ->assertSessionHasErrors('statut');

        $this->assertSame(StatutRendezVous::Confirme, $rdv->fresh()->statut);
    }

    public function test_une_valeur_de_statut_inconnue_est_refusee(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->receptionniste)
            ->patch(route('rendez-vous.statut', $rdv), ['statut' => 'bidon'])
            ->assertSessionHasErrors('statut');
    }

    public function test_l_annulation_garde_le_motif(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->receptionniste)
            ->patch(route('rendez-vous.statut', $rdv), ['statut' => 'annule', 'motif' => 'Cliente malade'])
            ->assertSessionHas('success');

        $rdv = $rdv->fresh();
        $this->assertSame(StatutRendezVous::Annule, $rdv->statut);
        $this->assertSame('Cliente malade', $rdv->motif_annulation);
    }

    public function test_un_rendez_vous_futur_ne_peut_pas_etre_marque_arrive(): void
    {
        $rdv = $this->existant(['date' => '2026-11-16']);

        $this->actingAs($this->receptionniste)
            ->patch(route('rendez-vous.statut', $rdv), ['statut' => 'client_arrive'])
            ->assertSessionHasErrors('statut');

        $this->assertSame(StatutRendezVous::Confirme, $rdv->fresh()->statut);
    }

    public function test_le_professionnel_marque_les_statuts_terrain_de_ses_rendez_vous_sans_annuler(): void
    {
        [$pro, $monEmploye] = $this->professionnel();
        $mien = $this->existant(['employe_id' => $monEmploye->id]);
        $autre = $this->existant(['heure_debut' => '14:00']);

        $this->actingAs($pro);

        $this->patch(route('rendez-vous.statut', $mien), ['statut' => 'client_arrive'])->assertSessionHas('success');
        $this->assertSame(StatutRendezVous::ClientArrive, $mien->fresh()->statut);

        $this->patch(route('rendez-vous.statut', $mien), ['statut' => 'annule'])->assertForbidden();
        $this->patch(route('rendez-vous.statut', $autre), ['statut' => 'client_arrive'])->assertForbidden();

        $this->assertSame(StatutRendezVous::Confirme, $autre->fresh()->statut);
    }

    public function test_la_fiche_propose_les_boutons_de_statut_possibles(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->receptionniste)
            ->get(route('rendez-vous.show', $rdv))
            ->assertSee('Client arrivé')
            ->assertSee('Annuler le rendez-vous')
            ->assertDontSee('Terminer');
    }

    // --- Modification et déplacement -----------------------------------------------

    public function test_le_formulaire_de_modification_est_prerempli(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->receptionniste)
            ->get(route('rendez-vous.edit', $rdv))
            ->assertOk()
            ->assertSee('Modifier le rendez-vous')
            ->assertSee('Aminata Zorro')
            ->assertSee('Coupe test');
    }

    public function test_la_receptionniste_deplace_un_rendez_vous(): void
    {
        $rdv = $this->existant();

        $this->actingAs($this->receptionniste)
            ->put(route('rendez-vous.update', $rdv), $this->donnees(['heure_debut' => '14:00']))
            ->assertRedirect(route('rendez-vous.show', $rdv));

        $this->assertSame('14:00:00', $rdv->fresh()->heure_debut);
        $this->assertSame('14:45:00', $rdv->fresh()->heure_fin);
        $this->assertDatabaseHas('historique_actions', ['action' => 'rendezvous.modifie', 'sujet_id' => $rdv->id]);
    }

    public function test_deplacer_sur_un_creneau_occupe_est_refuse(): void
    {
        $this->existant();                                   // 10:00 - 10:45
        $b = $this->existant(['heure_debut' => '14:00']);    // 14:00 - 14:45

        $this->actingAs($this->receptionniste)
            ->put(route('rendez-vous.update', $b), $this->donnees(['heure_debut' => '10:30']))
            ->assertSessionHasErrors('heure_debut');

        $this->assertSame('14:00:00', $b->fresh()->heure_debut);
    }

    public function test_un_rendez_vous_termine_ne_se_modifie_plus(): void
    {
        $rdv = $this->existant();
        $rdv->update(['statut' => StatutRendezVous::Termine]);

        $this->actingAs($this->receptionniste)
            ->put(route('rendez-vous.update', $rdv), $this->donnees(['heure_debut' => '14:00']))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('rendez-vous.update', $rdv), $this->donnees(['heure_debut' => '14:00']))
            ->assertSessionHasErrors('statut');

        $this->assertSame('10:00:00', $rdv->fresh()->heure_debut);
    }

    public function test_le_formulaire_de_modification_d_un_rendez_vous_termine_renvoie_vers_la_fiche(): void
    {
        $rdv = $this->existant();
        $rdv->update(['statut' => StatutRendezVous::Termine]);

        $this->actingAs($this->receptionniste)->get(route('rendez-vous.edit', $rdv))->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('rendez-vous.edit', $rdv))
            ->assertRedirect(route('rendez-vous.show', $rdv))
            ->assertSessionHas('error');
    }

    public function test_le_professionnel_ne_peut_pas_modifier(): void
    {
        [$pro, $monEmploye] = $this->professionnel();
        $mien = $this->existant(['employe_id' => $monEmploye->id]);

        $this->actingAs($pro);

        $this->get(route('rendez-vous.edit', $mien))->assertForbidden();
        $this->put(route('rendez-vous.update', $mien), $this->donnees(['employe_id' => $monEmploye->id]))->assertForbidden();
    }

    // --- Fiche client et suppression ----------------------------------------------

    public function test_la_fiche_client_affiche_ses_rendez_vous_et_le_bouton(): void
    {
        $this->existant();

        $this->actingAs($this->receptionniste)
            ->get(route('clients.show', $this->zorro))
            ->assertOk()
            ->assertSee('Prendre rendez-vous')
            ->assertSee('Coupe test')
            ->assertSee('Confirmé');
    }

    public function test_une_fiche_client_archivee_n_a_pas_de_bouton_de_rendez_vous(): void
    {
        $this->zorro->update(['is_active' => false]);

        $this->actingAs($this->receptionniste)
            ->get(route('clients.show', $this->zorro))
            ->assertOk()
            ->assertDontSee('Prendre rendez-vous');
    }

    public function test_il_n_existe_aucune_route_de_suppression(): void
    {
        $this->assertFalse(Route::has('rendez-vous.destroy'));
    }

    public function test_les_actions_sont_journalisees_avec_leur_auteur(): void
    {
        $this->actingAs($this->receptionniste)->post(route('rendez-vous.store'), $this->donnees());

        $this->assertDatabaseHas('historique_actions', [
            'user_id' => $this->receptionniste->id,
            'action' => 'rendezvous.cree',
        ]);
        $this->assertSame(1, HistoriqueAction::where('action', 'rendezvous.cree')->count());
    }
}