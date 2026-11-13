<?php

namespace Tests\Feature\Depenses;

use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\User;
use App\Support\Fcfa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class DepensePagesTest extends DepenseTestCase
{
    // --- Accès ---------------------------------------------------------------------

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $depense = $this->creer();

        $this->get(route('depenses.index'))->assertRedirect(route('login'));
        $this->get(route('depenses.create'))->assertRedirect(route('login'));
        $this->get(route('depenses.show', $depense))->assertRedirect(route('login'));
        $this->get(route('depenses.justificatif', $depense))->assertRedirect(route('login'));
    }

    public function test_la_receptionniste_et_le_professionnel_n_ont_acces_a_rien(): void
    {
        $depense = $this->creer([], UploadedFile::fake()->image('recu.jpg'));

        foreach ([$this->receptionniste, User::factory()->professionnel()->create()] as $user) {
            $this->actingAs($user);

            $this->get(route('depenses.index'))->assertForbidden();
            $this->get(route('depenses.create'))->assertForbidden();
            $this->post(route('depenses.store'), $this->donnees())->assertForbidden();
            $this->get(route('depenses.show', $depense))->assertForbidden();
            $this->get(route('depenses.edit', $depense))->assertForbidden();
            $this->put(route('depenses.update', $depense), $this->donnees(['montant' => 1]))->assertForbidden();
            $this->post(route('depenses.annuler', $depense), ['motif' => 'Saisie en double'])->assertForbidden();
            $this->get(route('depenses.justificatif', $depense))->assertForbidden();
        }

        $this->assertSame(1, Depense::count());
        $this->assertSame(150000, $depense->fresh()->montant);
        $this->assertFalse($depense->fresh()->estAnnulee());
    }

    public function test_il_n_existe_aucune_route_de_suppression(): void
    {
        $this->assertFalse(Route::has('depenses.destroy'));
    }

    public function test_le_menu_contient_les_depenses_pour_l_administratrice_seulement(): void
    {
        $this->actingAs($this->admin)->get('/')->assertSee(route('depenses.index'), false);

        $this->actingAs($this->receptionniste)->get('/')->assertDontSee(route('depenses.index'), false);
    }

    // --- Liste et totaux -----------------------------------------------------------

    public function test_la_liste_affiche_les_depenses_et_les_totaux_du_jour_et_du_mois(): void
    {
        $this->creer(['description' => 'Descr-JOUR', 'montant' => 150000]);
        $this->creer(['description' => 'Descr-SEMAINE', 'montant' => 25000, 'date' => '2026-11-02']);
        $this->creer(['description' => 'Descr-OCTOBRE', 'montant' => 40000, 'date' => '2026-10-15']);
        $annulee = $this->creer(['description' => 'Descr-ANNULEE', 'montant' => 99000]);
        $this->depenses()->annuler($annulee, 'Saisie en double', $this->admin);

        $this->actingAs($this->admin)
            ->get(route('depenses.index'))
            ->assertOk()
            ->assertSee('Descr-JOUR')
            ->assertSee('Descr-SEMAINE')
            ->assertDontSee('Descr-OCTOBRE')
            ->assertSee('Dépenses du jour')
            ->assertSee('Dépenses du mois')
            ->assertViewHas('totalAujourdhui', 150000)
            ->assertViewHas('totalMois', 175000)
            ->assertViewHas('totalPeriode', 175000);
    }

    public function test_les_depenses_annulees_sont_barrees_dans_la_liste(): void
    {
        $annulee = $this->creer(['description' => 'Descr-ANNULEE']);
        $this->depenses()->annuler($annulee, 'Saisie en double', $this->admin);

        $this->actingAs($this->admin)
            ->get(route('depenses.index'))
            ->assertSee('Descr-ANNULEE')
            ->assertSee('Annulée');
    }

    public function test_les_filtres_de_periode_de_categorie_et_de_statut(): void
    {
        $this->creer(['description' => 'Descr-ALPHA', 'date' => '2026-11-02']);
        $this->creer(['description' => 'Descr-BRAVO', 'categorie_depense_id' => $this->produits->id]);
        $annulee = $this->creer(['description' => 'Descr-CHARLIE']);
        $this->depenses()->annuler($annulee, 'Saisie en double', $this->admin);

        $this->actingAs($this->admin);

        $this->get(route('depenses.index', ['du' => '2026-11-02', 'au' => '2026-11-02']))
            ->assertSee('Descr-ALPHA')
            ->assertDontSee('Descr-BRAVO');

        $this->get(route('depenses.index', ['categorie' => $this->produits->id]))
            ->assertSee('Descr-BRAVO')
            ->assertDontSee('Descr-ALPHA');

        $this->get(route('depenses.index', ['statut' => 'valides']))
            ->assertSee('Descr-ALPHA')
            ->assertDontSee('Descr-CHARLIE');

        $this->get(route('depenses.index', ['statut' => 'annulees']))
            ->assertSee('Descr-CHARLIE')
            ->assertDontSee('Descr-ALPHA');
    }

    public function test_une_periode_inversee_est_remise_dans_l_ordre(): void
    {
        $this->creer(['description' => 'Descr-A', 'date' => '2026-11-02']);
        $this->creer(['description' => 'Descr-B']);

        $this->actingAs($this->admin)
            ->get(route('depenses.index', ['du' => '2026-11-09', 'au' => '2026-11-02']))
            ->assertSee('Descr-A')
            ->assertSee('Descr-B');
    }

    public function test_les_parametres_mal_formes_ne_provoquent_pas_d_erreur(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('depenses.index', ['du' => ['x'], 'au' => ['y'], 'categorie' => ['z'], 'statut' => ['w']]))->assertOk();
        $this->get(route('depenses.index', ['du' => '2026-02-31', 'au' => 'abc', 'categorie' => 'abc', 'statut' => 'bidon']))->assertOk();
        $this->get(route('depenses.index', ['du' => '1900-01-01', 'au' => '2026-11-09']))->assertOk();
    }

    public function test_la_liste_est_paginee_par_vingt_cinq(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->creer(['montant' => 100 + $i]);
        }

        $this->actingAs($this->admin)
            ->get(route('depenses.index'))
            ->assertOk()
            ->assertViewHas('depenses', fn ($page) => $page->count() === 25 && $page->total() === 26);
    }

    public function test_le_resultat_estime_n_est_visible_qu_avec_la_permission_de_chiffre_d_affaires(): void
    {
        $this->creer(['montant' => 150000]);

        $this->actingAs($this->admin)
            ->get(route('depenses.index'))
            ->assertSee('Résultat estimé')
            ->assertSee('Indicateur de gestion')
            ->assertSee('bénéfice comptable')
            ->assertViewHas('resultat', fn ($resultat) => $resultat['depenses'] === 150000 && $resultat['resultat'] === -150000);

        // Une personne qui gère les dépenses sans voir le chiffre d'affaires ne voit pas le résultat.
        $this->receptionniste->grantPermission('expenses.manage');

        $this->actingAs($this->receptionniste)
            ->get(route('depenses.index'))
            ->assertOk()
            ->assertDontSee('Résultat estimé')
            ->assertViewHas('resultat', fn ($resultat) => $resultat === null);
    }

    public function test_la_repartition_par_categorie_est_affichee(): void
    {
        $this->creer(['montant' => 150000]);
        $this->creer(['categorie_depense_id' => $this->produits->id, 'montant' => 25000]);

        $this->actingAs($this->admin)
            ->get(route('depenses.index'))
            ->assertSee('Par catégorie sur la période')
            ->assertSee(Fcfa::format(150000))
            ->assertSee(Fcfa::format(25000));
    }

    // --- Création ------------------------------------------------------------------

    public function test_le_formulaire_ne_propose_que_les_categories_actives(): void
    {
        CategorieDepense::factory()->inactive()->create(['nom' => 'Categorie-Fermee']);

        $this->actingAs($this->admin)
            ->get(route('depenses.create'))
            ->assertOk()
            ->assertSee('Nouvelle dépense')
            ->assertSee('Loyer')
            ->assertDontSee('Categorie-Fermee');
    }

    public function test_l_administratrice_enregistre_une_depense_avec_justificatif(): void
    {
        $reponse = $this->actingAs($this->admin)->post(
            route('depenses.store'),
            $this->donnees() + ['justificatif' => UploadedFile::fake()->image('recu.jpg')],
        );

        $depense = Depense::firstOrFail();

        $reponse->assertRedirect(route('depenses.show', $depense))->assertSessionHas('success');
        $this->assertSame(150000, $depense->montant);
        $this->assertTrue($depense->aUnJustificatif());
        Storage::disk('local')->assertExists($depense->justificatif);
    }

    public function test_une_depense_sans_justificatif_est_acceptee(): void
    {
        $this->actingAs($this->admin)->post(route('depenses.store'), $this->donnees())->assertSessionHasNoErrors();

        $this->assertNull(Depense::firstOrFail()->justificatif);
    }

    public function test_les_champs_invalides_sont_refuses(): void
    {
        $this->actingAs($this->admin);

        $cas = [
            'categorie_depense_id' => ['', '9999', 'abc'],
            'montant' => ['5000.5', '0', 'abc', '', '100000001'],
            'date' => ['', '2026-11-10', '2026-02-31', 'demain', '09/11/2026'],
            'description' => ['', str_repeat('a', 256)],
        ];

        foreach ($cas as $champ => $valeurs) {
            foreach ($valeurs as $valeur) {
                $this->post(route('depenses.store'), $this->donnees([$champ => $valeur]))->assertSessionHasErrors($champ);
            }
        }

        $this->assertSame(0, Depense::count());
    }

    public function test_les_justificatifs_dangereux_ou_trop_gros_sont_refuses(): void
    {
        $this->actingAs($this->admin);

        $fichiers = [
            UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            UploadedFile::fake()->create('shell.php', 10, 'text/x-php'),
            UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            UploadedFile::fake()->create('trop-gros.pdf', 2049, 'application/pdf'),
        ];

        foreach ($fichiers as $fichier) {
            $this->post(route('depenses.store'), $this->donnees() + ['justificatif' => $fichier])
                ->assertSessionHasErrors('justificatif');
        }

        $this->assertSame(0, Depense::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_un_pdf_est_accepte(): void
    {
        $this->actingAs($this->admin)
            ->post(route('depenses.store'), $this->donnees() + [
                'justificatif' => UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('pdf', Depense::firstOrFail()->extensionJustificatif());
    }

    // --- Fiche, modification, annulation -------------------------------------------

    public function test_la_fiche_affiche_les_details_et_les_actions(): void
    {
        $depense = $this->creer(['description' => 'Descr-FICHE']);

        $this->actingAs($this->admin)
            ->get(route('depenses.show', $depense))
            ->assertOk()
            ->assertSee('Descr-FICHE')
            ->assertSee('Loyer')
            ->assertSee(Fcfa::format(150000))
            ->assertSee('Annuler cette dépense')
            ->assertSee('Modifier');
    }

    public function test_une_depense_annulee_n_offre_plus_d_action(): void
    {
        $depense = $this->creer();
        $this->depenses()->annuler($depense, 'Saisie en double', $this->admin);

        $this->actingAs($this->admin)
            ->get(route('depenses.show', $depense))
            ->assertOk()
            ->assertSee('Cette dépense a été annulée')
            ->assertSee('Saisie en double')
            ->assertDontSee('Annuler cette dépense');
    }

    public function test_la_modification_depuis_l_ecran(): void
    {
        $depense = $this->creer();

        $this->actingAs($this->admin)->get(route('depenses.edit', $depense))->assertOk()->assertSee('Modifier la dépense');

        $this->put(route('depenses.update', $depense), $this->donnees(['montant' => 160000]))
            ->assertRedirect(route('depenses.show', $depense))
            ->assertSessionHas('success');

        $this->assertSame(160000, $depense->fresh()->montant);
    }

    public function test_une_depense_annulee_ne_s_ouvre_plus_en_modification(): void
    {
        $depense = $this->creer();
        $this->depenses()->annuler($depense, 'Saisie en double', $this->admin);

        $this->actingAs($this->admin);

        $this->get(route('depenses.edit', $depense))
            ->assertRedirect(route('depenses.show', $depense))
            ->assertSessionHas('error');

        $this->put(route('depenses.update', $depense), $this->donnees(['montant' => 1]))
            ->assertSessionHasErrors('depense');

        $this->assertSame(150000, $depense->fresh()->montant);
    }

    public function test_un_justificatif_ne_peut_pas_etre_remplace_depuis_l_ecran(): void
    {
        $depense = $this->creer([], UploadedFile::fake()->image('recu.jpg'));
        $chemin = $depense->justificatif;

        $this->actingAs($this->admin)
            ->put(route('depenses.update', $depense), $this->donnees() + ['justificatif' => UploadedFile::fake()->image('autre.jpg')])
            ->assertSessionHasErrors('justificatif');

        $this->assertSame($chemin, $depense->fresh()->justificatif);
        $this->assertCount(1, Storage::disk('local')->allFiles('justificatifs'));
    }

    public function test_le_formulaire_de_modification_ne_propose_pas_de_remplacer_le_justificatif(): void
    {
        $avec = $this->creer([], UploadedFile::fake()->image('recu.jpg'));
        $sans = $this->creer(['description' => 'Sans fichier']);

        $this->actingAs($this->admin);

        $this->get(route('depenses.edit', $avec))
            ->assertSee('Voir le justificatif enregistré')
            ->assertDontSee('name="justificatif"', false);

        $this->get(route('depenses.edit', $sans))
            ->assertSee('name="justificatif"', false);
    }

    public function test_l_annulation_depuis_l_ecran(): void
    {
        $depense = $this->creer();

        $this->actingAs($this->admin);

        $this->post(route('depenses.annuler', $depense), ['motif' => 'abc'])->assertSessionHasErrors('motif');
        $this->assertFalse($depense->fresh()->estAnnulee());

        $this->post(route('depenses.annuler', $depense), ['motif' => 'Saisie en double'])
            ->assertRedirect(route('depenses.show', $depense))
            ->assertSessionHas('success');
        $this->assertTrue($depense->fresh()->estAnnulee());

        // Une seconde annulation est refusée.
        $this->post(route('depenses.annuler', $depense), ['motif' => 'Encore une fois'])->assertSessionHasErrors('depense');
    }

    // --- Justificatif --------------------------------------------------------------

    public function test_le_justificatif_est_servi_a_l_administratrice_avec_une_protection(): void
    {
        $depense = $this->creer([], UploadedFile::fake()->image('recu.jpg'));

        $this->actingAs($this->admin)
            ->get(route('depenses.justificatif', $depense))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_une_depense_sans_justificatif_ou_au_fichier_manquant_donne_404(): void
    {
        $sans = $this->creer();
        $perdu = $this->creer(['description' => 'Fichier perdu'], UploadedFile::fake()->image('recu.jpg'));
        Storage::disk('local')->delete($perdu->justificatif);

        $this->actingAs($this->admin);

        $this->get(route('depenses.justificatif', $sans))->assertNotFound();
        $this->get(route('depenses.justificatif', $perdu))->assertNotFound();
    }
}