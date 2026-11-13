<?php

namespace Tests\Feature\Caisse;

use App\Enums\StatutRendezVous;
use App\Models\Client;
use App\Models\Employe;
use App\Models\Paiement;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\RendezVousService;
use App\Support\Fcfa;
use Illuminate\Support\Facades\Route;

class CaissePagesTest extends CaisseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->client->update(['prenom' => 'Aminata', 'nom' => 'Zorro']);
    }

    /** Rendez-vous réel : lundi 9 novembre, 10:00, confirmé, « Coupe test » à 5 000 FCFA. */
    private function reel(): RendezVous
    {
        return app(RendezVousService::class)->creer(
            $this->input(['client_id' => $this->client->id]),
            $this->receptionniste,
        );
    }

    private function donnees(RendezVous $rdv, array $override = []): array
    {
        return array_merge([
            'rendez_vous_id' => $rdv->id,
            'montant' => 5000,
            'mode_paiement_id' => $this->wave->id,
            'reference' => 'WV123',
        ], $override);
    }

    // --- Accès ---------------------------------------------------------------------

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $paiement = $this->payer($this->rdv(), 1000);

        $this->get(route('paiements.index'))->assertRedirect(route('login'));
        $this->get(route('paiements.impayes'))->assertRedirect(route('login'));
        $this->get(route('paiements.create'))->assertRedirect(route('login'));
        $this->get(route('paiements.recu', $paiement))->assertRedirect(route('login'));
    }

    public function test_le_professionnel_n_a_acces_a_rien(): void
    {
        $paiement = $this->payer($this->rdv(), 1000);
        $this->actingAs(User::factory()->professionnel()->create());

        $this->get(route('paiements.index'))->assertForbidden();
        $this->get(route('paiements.impayes'))->assertForbidden();
        $this->get(route('paiements.create'))->assertForbidden();
        $this->get(route('paiements.show', $paiement))->assertForbidden();
        $this->get(route('paiements.recu', $paiement))->assertForbidden();
        $this->getJson(route('paiements.clients', ['q' => 'Zor']))->assertForbidden();
        $this->post(route('paiements.store'), $this->donnees($this->rdv()))->assertForbidden();
        $this->post(route('paiements.annuler', $paiement), ['motif' => 'Erreur de saisie'])->assertForbidden();

        $this->assertSame(1, Paiement::count());
    }

    public function test_il_n_existe_aucune_route_de_modification_ni_de_suppression(): void
    {
        $this->assertFalse(Route::has('paiements.destroy'));
        $this->assertFalse(Route::has('paiements.update'));
        $this->assertFalse(Route::has('paiements.edit'));
    }

    public function test_le_menu_contient_la_caisse_selon_les_droits(): void
    {
        $this->actingAs($this->receptionniste)->get('/')->assertSee(route('paiements.index'), false);

        $this->actingAs(User::factory()->professionnel()->create())
            ->get('/')
            ->assertDontSee(route('paiements.index'), false);
    }

    // --- Liste ---------------------------------------------------------------------

    public function test_la_receptionniste_voit_les_paiements_du_jour_sans_les_totaux(): void
    {
        $this->payer($this->rdv(), 4000, null, $this->admin, ['date' => '2026-11-02', 'reference' => 'REF-VIEUX']);
        $this->payer($this->rdv(), 5000, $this->wave, null, ['reference' => 'REF-JOUR']);

        $this->actingAs($this->receptionniste)
            ->get(route('paiements.index', ['du' => '2026-11-01', 'au' => '2026-11-30']))
            ->assertOk()
            ->assertSee('REF-JOUR')
            ->assertDontSee('REF-VIEUX')
            ->assertDontSee('Total encaissé')
            ->assertSee('paiements du jour');
    }

    public function test_l_administratrice_voit_le_total_net_des_annulations(): void
    {
        $erreur = $this->payer($this->rdv(), 10000);                 // saisi par erreur, puis annulé
        $this->payer($this->rdv(), 5000, $this->wave);
        $this->caisse()->annuler($erreur, 'Erreur de saisie', $this->admin);
        $this->payer($erreur->rendezVous, 10000);                    // re-saisie correcte

        $this->actingAs($this->admin)
            ->get(route('paiements.index'))
            ->assertOk()
            ->assertSee('Total encaissé')
            ->assertSee(Fcfa::format(15000));
    }

    public function test_les_filtres_de_periode_de_mode_et_de_type(): void
    {
        $this->payer($this->rdv(), 4000, null, $this->admin, ['date' => '2026-11-02', 'reference' => 'REF-A']);
        $this->payer($this->rdv(), 6000, $this->wave, null, ['reference' => 'REF-B']);

        $this->actingAs($this->admin);

        $this->get(route('paiements.index', ['du' => '2026-11-02', 'au' => '2026-11-02']))
            ->assertSee('REF-A')
            ->assertDontSee('REF-B');

        $this->get(route('paiements.index', ['mode' => $this->wave->id]))
            ->assertSee('REF-B')
            ->assertDontSee('REF-A');

        $this->get(route('paiements.index', ['type' => 'annulation']))
            ->assertSee('Aucun paiement sur cette période.');
    }

    public function test_une_periode_inversee_est_remise_dans_l_ordre(): void
    {
        $this->payer($this->rdv(), 4000, null, $this->admin, ['date' => '2026-11-02', 'reference' => 'REF-A']);
        $this->payer($this->rdv(), 6000, $this->wave, null, ['reference' => 'REF-B']);

        $this->actingAs($this->admin)
            ->get(route('paiements.index', ['du' => '2026-11-09', 'au' => '2026-11-02']))
            ->assertSee('REF-A')
            ->assertSee('REF-B');
    }

    public function test_les_parametres_mal_formes_ne_provoquent_pas_d_erreur(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('paiements.index', ['du' => ['x'], 'au' => ['y'], 'mode' => ['z'], 'type' => ['w']]))->assertOk();
        $this->get(route('paiements.index', ['du' => '2026-02-31', 'au' => 'abc', 'mode' => 'abc', 'type' => 'bidon']))->assertOk();
        $this->get(route('paiements.index', ['du' => '1900-01-01', 'au' => '2026-11-09']))->assertOk();
    }

    public function test_la_liste_distingue_un_paiement_annule_et_son_annulation(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);

        $this->actingAs($this->admin)
            ->get(route('paiements.index'))
            ->assertDontSee('Annulé');

        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->get(route('paiements.index'))
            ->assertSee('Annulé')
            ->assertSee(Fcfa::format(-8000));
    }

    public function test_la_liste_est_paginee_par_vingt_cinq(): void
    {
        $rdv = $this->rdv();

        for ($i = 0; $i < 26; $i++) {
            $this->payer($rdv, 100);
        }

        $this->actingAs($this->admin)
            ->get(route('paiements.index'))
            ->assertOk()
            ->assertViewHas('paiements', fn ($page) => $page->count() === 25 && $page->total() === 26);
    }

    // --- Formulaire et encaissement ------------------------------------------------

    public function test_le_formulaire_affiche_le_reste_a_payer(): void
    {
        $rdv = $this->rdv();
        $this->payer($rdv, 5000);

        $this->actingAs($this->receptionniste)
            ->get(route('paiements.create', ['rendez_vous' => $rdv->id]))
            ->assertOk()
            ->assertSee('Encaisser un rendez-vous')
            ->assertSee('Aminata Zorro')
            ->assertSee('Reste à payer')
            ->assertSee(Fcfa::format(15000))
            ->assertSee('Solder')
            ->assertSee('Enregistrer le paiement');
    }

    public function test_le_formulaire_refuse_un_rendez_vous_non_encaissable_ou_deja_paye(): void
    {
        $this->actingAs($this->receptionniste);

        $enAttente = $this->rdv(StatutRendezVous::EnAttente);
        $this->get(route('paiements.create', ['rendez_vous' => $enAttente->id]))
            ->assertRedirect(route('rendez-vous.show', $enAttente))
            ->assertSessionHas('error');

        $solde = $this->rdv();
        $this->payer($solde, 20000);
        $this->get(route('paiements.create', ['rendez_vous' => $solde->id]))
            ->assertRedirect(route('rendez-vous.show', $solde))
            ->assertSessionHas('error');

        $gratuit = $this->rdv(StatutRendezVous::Termine, ['prix_prevu' => 0]);
        $this->get(route('paiements.create', ['rendez_vous' => $gratuit->id]))
            ->assertRedirect(route('rendez-vous.show', $gratuit))
            ->assertSessionHas('error');

        $this->get(route('paiements.create', ['rendez_vous' => 9999]))->assertNotFound();
    }

    public function test_le_formulaire_de_vente_sans_rendez_vous(): void
    {
        $this->actingAs($this->receptionniste)
            ->get(route('paiements.create'))
            ->assertOk()
            ->assertSee('Vente sans rendez-vous')
            ->assertSee('Rechercher un client');

        // Un paramètre mal formé est simplement ignoré.
        $this->get(route('paiements.create', ['rendez_vous' => ['x'], 'client' => ['y']]))->assertOk();
    }

    public function test_la_date_n_est_proposee_qu_a_ceux_qui_peuvent_corriger(): void
    {
        $rdv = $this->rdv();

        $this->actingAs($this->receptionniste)
            ->get(route('paiements.create', ['rendez_vous' => $rdv->id]))
            ->assertDontSee('Date du paiement');

        $this->actingAs($this->admin)
            ->get(route('paiements.create', ['rendez_vous' => $rdv->id]))
            ->assertSee('Date du paiement');
    }

    public function test_la_receptionniste_encaisse_un_rendez_vous(): void
    {
        $rdv = $this->rdv();

        $reponse = $this->actingAs($this->receptionniste)->post(route('paiements.store'), $this->donnees($rdv));

        $paiement = Paiement::firstOrFail();

        $reponse->assertRedirect(route('paiements.show', $paiement))->assertSessionHas('success');
        $this->assertSame(5000, $paiement->montant);
        $this->assertSame($this->wave->id, $paiement->mode_paiement_id);
        $this->assertSame('WV123', $paiement->reference);
        $this->assertSame(15000, $paiement->reste_apres);
        $this->assertSame($this->receptionniste->id, $paiement->user_id);
    }

    public function test_un_trop_percu_est_refuse(): void
    {
        $rdv = $this->rdv();

        $this->actingAs($this->receptionniste)
            ->post(route('paiements.store'), $this->donnees($rdv, ['montant' => 25000]))
            ->assertSessionHasErrors('montant');

        $this->assertSame(0, Paiement::count());
    }

    public function test_une_vente_sans_rendez_vous_est_enregistree(): void
    {
        $this->actingAs($this->receptionniste)->post(route('paiements.store'), [
            'client_id' => $this->client->id,
            'libelle' => 'Vente de shampoing',
            'montant' => 3000,
            'mode_paiement_id' => $this->especes->id,
        ])->assertSessionHasNoErrors();

        $paiement = Paiement::firstOrFail();

        $this->assertNull($paiement->rendez_vous_id);
        $this->assertSame('Vente de shampoing', $paiement->libelle);
        $this->assertSame(3000, $paiement->montant);
    }

    public function test_une_vente_sans_client_ni_libelle_est_refusee(): void
    {
        $this->actingAs($this->receptionniste);

        $this->post(route('paiements.store'), ['libelle' => 'Produit', 'montant' => 3000, 'mode_paiement_id' => $this->especes->id])
            ->assertSessionHasErrors('client_id');

        $this->post(route('paiements.store'), ['client_id' => $this->client->id, 'montant' => 3000, 'mode_paiement_id' => $this->especes->id])
            ->assertSessionHasErrors('libelle');

        $this->assertSame(0, Paiement::count());
    }

    public function test_les_champs_invalides_sont_refuses(): void
    {
        $rdv = $this->rdv();
        $this->actingAs($this->receptionniste);

        foreach (['5000.5', '0', 'abc', '', '10000001'] as $montant) {
            $this->post(route('paiements.store'), $this->donnees($rdv, ['montant' => $montant]))->assertSessionHasErrors('montant');
        }

        foreach (['', '9999', 'abc'] as $mode) {
            $this->post(route('paiements.store'), $this->donnees($rdv, ['mode_paiement_id' => $mode]))->assertSessionHasErrors('mode_paiement_id');
        }

        $this->post(route('paiements.store'), $this->donnees($rdv, ['date' => 'bidon']))->assertSessionHasErrors('date');
        $this->post(route('paiements.store'), $this->donnees($rdv, ['reference' => str_repeat('a', 101)]))->assertSessionHasErrors('reference');
        $this->post(route('paiements.store'), $this->donnees($rdv, ['rendez_vous_id' => 9999]))->assertSessionHasErrors('rendez_vous_id');

        $this->assertSame(0, Paiement::count());
    }

    public function test_seule_une_personne_autorisee_peut_choisir_une_autre_date(): void
    {
        $rdv = $this->rdv();

        $this->actingAs($this->receptionniste)
            ->post(route('paiements.store'), $this->donnees($rdv, ['date' => '2026-11-02']))
            ->assertSessionHasErrors('date');
        $this->assertSame(0, Paiement::count());

        $this->actingAs($this->admin)
            ->post(route('paiements.store'), $this->donnees($rdv, ['date' => '2026-11-02']))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-11-02', Paiement::firstOrFail()->date->toDateString());
    }

    // --- Fiche, annulation, correction ---------------------------------------------

    public function test_la_fiche_d_un_paiement_et_ses_actions_selon_les_droits(): void
    {
        $paiement = $this->payer($this->rdv(), 5000, $this->wave, null, ['reference' => 'WV123']);

        $this->actingAs($this->receptionniste)
            ->get(route('paiements.show', $paiement))
            ->assertOk()
            ->assertSee($paiement->numero())
            ->assertSee('Aminata Zorro')
            ->assertSee(Fcfa::format(5000))
            ->assertSee('WV123')
            ->assertSee('Voir le reçu')
            ->assertDontSee('Annuler ce paiement')
            ->assertDontSee('Corriger ce paiement');

        $this->actingAs($this->admin)
            ->get(route('paiements.show', $paiement))
            ->assertSee('Annuler ce paiement')
            ->assertSee('Corriger ce paiement');
    }

    public function test_un_paiement_annule_et_son_annulation_n_offrent_plus_d_actions(): void
    {
        $paiement = $this->payer($this->rdv(), 5000);
        $annulation = $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->actingAs($this->admin);

        $this->get(route('paiements.show', $paiement))
            ->assertOk()
            ->assertSee('Ce paiement a été annulé')
            ->assertDontSee('Annuler ce paiement');

        $this->get(route('paiements.show', $annulation))
            ->assertOk()
            ->assertSee('Erreur de saisie')
            ->assertDontSee('Annuler ce paiement');
    }

    public function test_l_annulation_depuis_l_ecran(): void
    {
        $paiement = $this->payer($this->rdv(), 8000);

        $this->actingAs($this->receptionniste)
            ->post(route('paiements.annuler', $paiement), ['motif' => 'Erreur de saisie'])
            ->assertForbidden();
        $this->assertFalse($paiement->fresh()->estAnnule());

        $this->actingAs($this->admin);

        $this->post(route('paiements.annuler', $paiement), ['motif' => 'abc'])->assertSessionHasErrors('motif');
        $this->assertFalse($paiement->fresh()->estAnnule());

        $this->post(route('paiements.annuler', $paiement), ['motif' => 'Erreur de saisie'])
            ->assertRedirect(route('paiements.show', $paiement))
            ->assertSessionHas('success');
        $this->assertTrue($paiement->fresh()->estAnnule());

        // Une seconde annulation est refusée.
        $this->post(route('paiements.annuler', $paiement), ['motif' => 'Encore une fois'])
            ->assertSessionHasErrors('paiement');
        $this->assertSame(2, Paiement::count());
    }

    public function test_la_correction_depuis_l_ecran(): void
    {
        $rdv = $this->rdv();
        $paiement = $this->payer($rdv, 10000);

        $donnees = ['montant' => 8000, 'mode_paiement_id' => $this->wave->id, 'reference' => 'WV9', 'motif' => 'Mauvais montant'];

        $this->actingAs($this->receptionniste)
            ->post(route('paiements.corriger', $paiement), $donnees)
            ->assertForbidden();

        $this->actingAs($this->admin);

        // Un nouveau montant trop élevé est refusé : rien ne bouge.
        $this->post(route('paiements.corriger', $paiement), array_merge($donnees, ['montant' => 25000]))
            ->assertSessionHasErrors('montant');
        $this->assertSame(1, Paiement::count());
        $this->assertFalse($paiement->fresh()->estAnnule());

        $this->post(route('paiements.corriger', $paiement), $donnees)->assertSessionHasNoErrors();

        $corrige = Paiement::orderByDesc('id')->firstOrFail();

        $this->assertSame(3, Paiement::count());
        $this->assertSame(8000, $corrige->montant);
        $this->assertSame(8000, $rdv->montantPaye());
        $this->assertTrue($paiement->fresh()->estAnnule());
    }

    // --- Reçu ----------------------------------------------------------------------

    public function test_le_recu_est_imprimable(): void
    {
        $rdv = $this->reel();
        $paiement = $this->payer($rdv, 2000);

        $this->actingAs($this->receptionniste)
            ->get(route('paiements.recu', $paiement))
            ->assertOk()
            ->assertSee($paiement->numero())
            ->assertSee('Reçu de paiement')
            ->assertSee('Aminata Zorro')
            ->assertSee('Coupe test')
            ->assertSee(Fcfa::format(5000))
            ->assertSee(Fcfa::format(2000))
            ->assertSee(Fcfa::format(3000))
            ->assertSee('Reste à payer')
            ->assertSee('document non fiscal')
            ->assertSee('Imprimer');
    }

    public function test_le_recu_d_une_annulation_et_d_un_paiement_annule(): void
    {
        $paiement = $this->payer($this->rdv(), 5000);
        $annulation = $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->actingAs($this->admin);

        $this->get(route('paiements.recu', $annulation))
            ->assertOk()
            ->assertSee('ANNULATION')
            ->assertSee($paiement->numero())
            ->assertSee('Erreur de saisie');

        $this->get(route('paiements.recu', $paiement))
            ->assertOk()
            ->assertSee('Ce paiement a été annulé');
    }

    public function test_le_recu_affiche_les_coordonnees_du_salon_si_elles_existent(): void
    {
        config(['salon.recu.adresse' => 'Rue test, Dakar', 'salon.recu.telephone' => '33 000 00 00']);

        $paiement = $this->payer($this->rdv(), 1000);

        $this->actingAs($this->receptionniste)
            ->get(route('paiements.recu', $paiement))
            ->assertSee('Rue test, Dakar')
            ->assertSee('33 000 00 00');
    }

    // --- Impayés -------------------------------------------------------------------

    public function test_les_impayes_et_leur_total(): void
    {
        $this->rdv();                                   // reste 20 000
        $partiel = $this->rdv();
        $this->payer($partiel, 5000);                   // reste 15 000
        $solde = $this->rdv();
        $this->payer($solde, 20000);                    // soldé : absent

        $this->actingAs($this->admin)
            ->get(route('paiements.impayes'))
            ->assertOk()
            ->assertSee('Aminata Zorro')
            ->assertSee('Total des impayés')
            ->assertSee(Fcfa::format(35000));

        $this->actingAs($this->receptionniste)
            ->get(route('paiements.impayes'))
            ->assertOk()
            ->assertSee('Aminata Zorro')
            ->assertSee('Encaisser')
            ->assertDontSee('Total des impayés');
    }

    public function test_aucun_impaye(): void
    {
        $this->actingAs($this->receptionniste)
            ->get(route('paiements.impayes'))
            ->assertOk()
            ->assertSee('Aucun impayé.');
    }

    // --- Intégration : fiche rendez-vous, fiche client, recherche ------------------

    public function test_la_fiche_du_rendez_vous_affiche_la_caisse(): void
    {
        $rdv = $this->reel();

        $this->actingAs($this->receptionniste)
            ->get(route('rendez-vous.show', $rdv))
            ->assertOk()
            ->assertSee('Reste à payer')
            ->assertSee('Encaisser')
            ->assertSee(Fcfa::format(5000));

        $this->payer($rdv, 5000);

        $this->get(route('rendez-vous.show', $rdv))
            ->assertSee('Entièrement payé')
            ->assertDontSee('Encaisser');
    }

    public function test_un_rendez_vous_paye_ne_propose_plus_l_annulation(): void
    {
        $rdv = $this->reel();
        $this->payer($rdv, 2000);

        $this->actingAs($this->admin)
            ->get(route('rendez-vous.show', $rdv))
            ->assertOk()
            ->assertDontSee('Annuler le rendez-vous')
            ->assertSee("faites d'abord annuler le paiement", false);
    }

    public function test_le_professionnel_ne_voit_pas_la_caisse_sur_la_fiche_du_rendez_vous(): void
    {
        $pro = User::factory()->professionnel()->create();
        $monEmploye = Employe::factory()->create(['user_id' => $pro->id]);
        $rdv = RendezVous::factory()->create(['employe_id' => $monEmploye->id, 'client_id' => $this->client->id]);

        $this->actingAs($pro)
            ->get(route('rendez-vous.show', $rdv))
            ->assertOk()
            ->assertDontSee('Reste à payer')
            ->assertDontSee('Encaisser');
    }

    public function test_la_fiche_client_affiche_les_paiements(): void
    {
        $paiement = $this->payer($this->rdv(), 5000);

        $this->actingAs($this->receptionniste)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee('Total payé')
            ->assertSee(Fcfa::format(5000))
            ->assertSee($paiement->numero())
            ->assertSee('dès que ces modules seront en place');
    }

    public function test_la_recherche_de_clients_pour_une_vente(): void
    {
        Client::factory()->inactif()->create(['prenom' => 'Zorra', 'nom' => 'Archivee']);
        $this->actingAs($this->receptionniste);

        $this->getJson(route('paiements.clients', ['q' => 'Zor']))
            ->assertOk()
            ->assertJsonCount(1, 'clients')
            ->assertJsonPath('clients.0.nom', 'Aminata Zorro');

        $this->getJson(route('paiements.clients', ['q' => 'Z']))->assertJsonCount(0, 'clients');
        $this->getJson(route('paiements.clients', ['q' => ['x']]))->assertJsonCount(0, 'clients');
    }

    public function test_les_statuts_encaissables(): void
    {
        $this->assertTrue($this->caisse()->estEncaissable($this->rdv(StatutRendezVous::Confirme)));
        $this->assertTrue($this->caisse()->estEncaissable($this->rdv(StatutRendezVous::Termine)));
        $this->assertFalse($this->caisse()->estEncaissable($this->rdv(StatutRendezVous::EnAttente)));
        $this->assertFalse($this->caisse()->estEncaissable($this->rdv(StatutRendezVous::Annule)));
        $this->assertFalse($this->caisse()->estEncaissable($this->rdv(StatutRendezVous::ClientAbsent)));
    }
}