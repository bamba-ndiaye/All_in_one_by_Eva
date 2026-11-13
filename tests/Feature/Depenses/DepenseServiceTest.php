<?php

namespace Tests\Feature\Depenses;

use App\Models\Depense;
use App\Models\HistoriqueAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DepenseServiceTest extends DepenseTestCase
{
    public function test_une_depense_est_enregistree_et_journalisee_sans_sa_description(): void
    {
        $depense = $this->creer([
            'categorie_depense_id' => $this->produits->id,
            'montant' => '85000',
            'description' => '  Salaire secret de Awa  ',
        ]);

        $this->assertSame(85000, $depense->montant);
        $this->assertSame($this->produits->id, $depense->categorie_depense_id);
        $this->assertSame('2026-11-09', $depense->date->toDateString());
        $this->assertSame('Salaire secret de Awa', $depense->description);
        $this->assertSame($this->admin->id, $depense->user_id);
        $this->assertNull($depense->justificatif);
        $this->assertFalse($depense->estAnnulee());

        $log = HistoriqueAction::where('action', 'depense.creee')->firstOrFail();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($depense->id, $log->sujet_id);
        $this->assertSame(85000, $log->nouvelles_valeurs['montant']);
        $this->assertStringNotContainsString('secret', json_encode([$log->description, $log->nouvelles_valeurs]));
    }

    public function test_les_montants_invalides_sont_refuses(): void
    {
        foreach ([0, -5, 'abc', '5000.5', 5000.5, null, '', 100_000_001] as $montant) {
            $this->assertValidationError('montant', fn () => $this->creer(['montant' => $montant]));
        }

        $this->assertSame(0, Depense::count());
    }

    public function test_un_montant_en_texte_entier_est_accepte(): void
    {
        $this->assertSame(7500, $this->creer(['montant' => '7500'])->montant);
    }

    public function test_les_dates_invalides_ou_futures_sont_refusees(): void
    {
        foreach (['2026-11-10', '2026-02-31', 'hier', '', null, '1999-12-31'] as $date) {
            $this->assertValidationError('date', fn () => $this->creer(['date' => $date]));
        }

        $this->assertSame(0, Depense::count());
    }

    public function test_aujourd_hui_et_une_date_passee_sont_acceptes(): void
    {
        $this->assertSame('2026-11-09', $this->creer(['date' => '2026-11-09'])->date->toDateString());
        $this->assertSame('2026-01-15', $this->creer(['date' => '2026-01-15'])->date->toDateString());
    }

    public function test_la_description_est_obligatoire_et_limitee(): void
    {
        foreach (['', '   ', null, str_repeat('a', 256)] as $description) {
            $this->assertValidationError('description', fn () => $this->creer(['description' => $description]));
        }

        $this->assertSame(0, Depense::count());
    }

    public function test_la_categorie_doit_exister_et_etre_active(): void
    {
        $inactive = \App\Models\CategorieDepense::factory()->inactive()->create();

        foreach ([$inactive->id, 9999, 0, null, ''] as $categorie) {
            $this->assertValidationError('categorie_depense_id', fn () => $this->creer(['categorie_depense_id' => $categorie]));
        }

        $this->assertSame(0, Depense::count());
    }

    public function test_seule_une_personne_autorisee_peut_enregistrer(): void
    {
        $refuses = [
            $this->receptionniste,
            User::factory()->professionnel()->create(),
            User::factory()->administratrice()->inactif()->create(),
        ];

        foreach ($refuses as $user) {
            try {
                $this->creer([], null, $user);
                $this->fail("L'enregistrement aurait dû être refusé.");
            } catch (AuthorizationException) {
                // attendu
            }
        }

        $this->assertSame(0, Depense::count());
    }

    public function test_un_droit_individuel_permet_d_enregistrer(): void
    {
        $this->receptionniste->grantPermission('expenses.manage');

        $this->assertSame(150000, $this->creer([], null, $this->receptionniste)->montant);
    }

    public function test_le_justificatif_est_stocke_sur_le_disque_prive(): void
    {
        $depense = $this->creer([], UploadedFile::fake()->image('recu.jpg'));

        $this->assertTrue($depense->aUnJustificatif());
        $this->assertStringStartsWith('justificatifs/', $depense->justificatif);
        Storage::disk('local')->assertExists($depense->justificatif);
        $this->assertSame('jpg', $depense->extensionJustificatif());
    }

    public function test_modifier_le_montant_journalise_l_ancienne_et_la_nouvelle_valeur(): void
    {
        $depense = $this->creer();

        $this->depenses()->modifier($depense, $this->donnees(['montant' => 160000]), null, $this->admin);

        $this->assertSame(160000, $depense->fresh()->montant);

        $log = HistoriqueAction::where('action', 'depense.modifiee')->firstOrFail();

        $this->assertEquals(['montant' => 150000], $log->anciennes_valeurs);
        $this->assertEquals(['montant' => 160000], $log->nouvelles_valeurs);
    }

    public function test_modifier_la_categorie_et_la_date_est_journalise(): void
    {
        $depense = $this->creer();

        $this->depenses()->modifier($depense, $this->donnees([
            'categorie_depense_id' => $this->produits->id,
            'date' => '2026-11-03',
        ]), null, $this->admin);

        $log = HistoriqueAction::where('action', 'depense.modifiee')->firstOrFail();

        $this->assertSame($this->loyer->id, $log->anciennes_valeurs['categorie_depense_id']);
        $this->assertSame($this->produits->id, $log->nouvelles_valeurs['categorie_depense_id']);
        $this->assertSame('2026-11-09', $log->anciennes_valeurs['date']);
        $this->assertSame('2026-11-03', $log->nouvelles_valeurs['date']);
    }

    public function test_une_modification_sans_changement_n_ecrit_rien_dans_le_journal(): void
    {
        $depense = $this->creer();

        $this->depenses()->modifier($depense, $this->donnees(), null, $this->admin);

        $this->assertSame(0, HistoriqueAction::where('action', 'depense.modifiee')->count());
    }

    public function test_modifier_la_description_est_journalise_sans_son_contenu(): void
    {
        $depense = $this->creer();

        $this->depenses()->modifier($depense, $this->donnees(['description' => 'Prime nominative de Binta']), null, $this->admin);

        $this->assertSame('Prime nominative de Binta', $depense->fresh()->description);

        $log = HistoriqueAction::where('action', 'depense.modifiee')->firstOrFail();

        $this->assertTrue($log->nouvelles_valeurs['description_modifiee']);
        $this->assertStringNotContainsString('Binta', json_encode([$log->description, $log->nouvelles_valeurs]));
    }

    public function test_une_modification_invalide_ne_change_rien(): void
    {
        $depense = $this->creer();

        $this->assertValidationError('montant', fn () => $this->depenses()->modifier($depense, $this->donnees(['montant' => 0]), null, $this->admin));

        $this->assertSame(150000, $depense->fresh()->montant);
        $this->assertSame(0, HistoriqueAction::where('action', 'depense.modifiee')->count());
    }

    public function test_une_depense_annulee_ne_se_modifie_plus(): void
    {
        $depense = $this->creer();
        $this->depenses()->annuler($depense, 'Saisie en double', $this->admin);

        $this->assertValidationError('depense', fn () => $this->depenses()->modifier($depense, $this->donnees(['montant' => 1]), null, $this->admin));

        $this->assertSame(150000, $depense->fresh()->montant);
    }

    public function test_une_categorie_desactivee_depuis_reste_acceptee_pour_la_depense_existante(): void
    {
        $depense = $this->creer();
        $this->loyer->update(['is_active' => false]);

        $this->depenses()->modifier($depense, $this->donnees(['montant' => 155000]), null, $this->admin);

        $this->assertSame(155000, $depense->fresh()->montant);

        // En revanche, on ne peut pas choisir une catégorie désactivée pour une nouvelle dépense.
        $this->assertValidationError('categorie_depense_id', fn () => $this->creer());
    }

    public function test_un_justificatif_peut_etre_ajoute_apres_coup_mais_jamais_remplace(): void
    {
        $depense = $this->creer();
        $this->assertNull($depense->justificatif);

        $this->depenses()->modifier($depense, $this->donnees(), UploadedFile::fake()->image('recu.jpg'), $this->admin);

        $chemin = $depense->fresh()->justificatif;
        $this->assertNotNull($chemin);
        Storage::disk('local')->assertExists($chemin);

        $log = HistoriqueAction::where('action', 'depense.modifiee')->firstOrFail();
        $this->assertTrue($log->nouvelles_valeurs['justificatif_ajoute']);

        $this->assertValidationError('justificatif', fn () => $this->depenses()->modifier(
            $depense,
            $this->donnees(),
            UploadedFile::fake()->image('autre.jpg'),
            $this->admin,
        ));

        $this->assertSame($chemin, $depense->fresh()->justificatif);
        Storage::disk('local')->assertExists($chemin);
        $this->assertCount(1, Storage::disk('local')->allFiles('justificatifs'));
    }

    public function test_l_annulation_garde_le_motif_et_n_apparait_pas_dans_le_journal(): void
    {
        $depense = $this->creer();

        $this->depenses()->annuler($depense, '  Dépense saisie en double  ', $this->admin);

        $depense = $depense->fresh();

        $this->assertTrue($depense->estAnnulee());
        $this->assertSame($this->admin->id, $depense->annulee_par);
        $this->assertSame('Dépense saisie en double', $depense->motif_annulation);

        $log = HistoriqueAction::where('action', 'depense.annulee')->firstOrFail();

        $this->assertSame($depense->id, $log->sujet_id);
        $this->assertStringNotContainsString('double', json_encode([$log->description, $log->nouvelles_valeurs]));
    }

    public function test_une_depense_ne_s_annule_qu_une_fois(): void
    {
        $depense = $this->creer();
        $this->depenses()->annuler($depense, 'Saisie en double', $this->admin);

        $this->assertValidationError('depense', fn () => $this->depenses()->annuler($depense, 'Encore une fois', $this->admin));

        $this->assertSame(1, HistoriqueAction::where('action', 'depense.annulee')->count());
    }

    public function test_le_motif_d_annulation_est_obligatoire(): void
    {
        $depense = $this->creer();

        foreach (['', '   ', 'abc', str_repeat('a', 256)] as $motif) {
            $this->assertValidationError('motif', fn () => $this->depenses()->annuler($depense, $motif, $this->admin));
        }

        $this->assertFalse($depense->fresh()->estAnnulee());
    }

    public function test_seule_une_personne_autorisee_peut_annuler_ou_modifier(): void
    {
        $depense = $this->creer();

        try {
            $this->depenses()->annuler($depense, 'Saisie en double', $this->receptionniste);
            $this->fail("L'annulation aurait dû être refusée.");
        } catch (AuthorizationException) {
            // attendu
        }

        try {
            $this->depenses()->modifier($depense, $this->donnees(['montant' => 1]), null, $this->receptionniste);
            $this->fail('La modification aurait dû être refusée.');
        } catch (AuthorizationException) {
            // attendu
        }

        $this->assertFalse($depense->fresh()->estAnnulee());
        $this->assertSame(150000, $depense->fresh()->montant);
    }
}