<?php

namespace Tests\Feature\Stock;

use App\Enums\TypeMouvementStock;
use App\Models\HistoriqueAction;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\User;
use App\Services\StockService;
use App\Support\Fcfa;
use Database\Seeders\DemoProduitsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->administratrice()->create();
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function produit(int $quantite = 10, array $attributs = []): Produit
    {
        return $this->stock()->creer(array_merge([
            'nom' => 'Shampoing kératine',
            'reference' => null,
            'unite' => 'flacon',
            'prix_achat' => 4500,
            'prix_vente' => 7500,
            'seuil_alerte' => 3,
        ], $attributs), $quantite, $this->admin);
    }

    private function donnees(array $override = []): array
    {
        return array_merge([
            'nom' => 'Huile de coco',
            'reference' => 'HC-01',
            'unite' => 'flacon',
            'prix_achat' => 2000,
            'prix_vente' => 4000,
            'seuil_alerte' => 2,
            'quantite_initiale' => 6,
        ], $override);
    }

    // --- Accès ---------------------------------------------------------------------

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('produits.index'))->assertRedirect(route('login'));
    }

    public function test_sans_droit_stock_l_acces_est_refuse(): void
    {
        $this->actingAs(User::factory()->receptionniste()->create());

        $this->get(route('produits.index'))->assertForbidden();
        $this->post(route('produits.store'), $this->donnees())->assertForbidden();
    }

    public function test_stock_view_consulte_sans_voir_les_couts_ni_modifier(): void
    {
        $produit = $this->produit();
        $user = User::factory()->receptionniste()->create();
        $user->grantPermission('stock.view');

        $this->actingAs($user);

        $this->get(route('produits.index'))->assertOk()->assertSee('Shampoing kératine')->assertDontSee("Prix d'achat")->assertDontSee('Valeur du stock');
        $this->get(route('produits.show', $produit))->assertOk()->assertDontSee('Nouveau mouvement');
        $this->get(route('produits.create'))->assertForbidden();
        $this->post(route('produits.mouvement', $produit), ['type' => 'vente', 'quantite' => 1])->assertForbidden();

        $this->assertSame(10, $produit->refresh()->quantite);
    }

    // --- Produits ------------------------------------------------------------------

    public function test_creer_un_produit_avec_stock_initial(): void
    {
        $this->actingAs($this->admin)
            ->post(route('produits.store'), $this->donnees())
            ->assertRedirect();

        $produit = Produit::where('reference', 'HC-01')->firstOrFail();

        $this->assertSame(6, $produit->quantite);
        $this->assertSame(1, $produit->mouvements()->count());

        $mouvement = $produit->mouvements()->first();
        $this->assertSame(TypeMouvementStock::Entree, $mouvement->type);
        $this->assertSame('Stock initial', $mouvement->motif);
        $this->assertSame(6, $mouvement->quantite_apres);
    }

    public function test_creer_sans_quantite_ne_cree_aucun_mouvement(): void
    {
        $this->actingAs($this->admin)
            ->post(route('produits.store'), $this->donnees(['quantite_initiale' => '']))
            ->assertRedirect();

        $produit = Produit::where('reference', 'HC-01')->firstOrFail();
        $this->assertSame(0, $produit->quantite);
        $this->assertSame(0, $produit->mouvements()->count());
    }

    public function test_validation_du_produit(): void
    {
        $this->produit(attributs: ['reference' => 'HC-01']);

        $this->actingAs($this->admin)
            ->post(route('produits.store'), $this->donnees([
                'nom' => '',
                'prix_achat' => '12.5',
                'seuil_alerte' => -1,
                'quantite_initiale' => 'abc',
            ]))
            ->assertSessionHasErrors(['nom', 'reference', 'prix_achat', 'seuil_alerte', 'quantite_initiale']);
    }

    public function test_modifier_la_fiche_ne_change_jamais_la_quantite(): void
    {
        $produit = $this->produit(10);

        $this->actingAs($this->admin)
            ->put(route('produits.update', $produit), $this->donnees(['nom' => 'Shampoing renommé', 'quantite' => 999, 'quantite_initiale' => 999]))
            ->assertRedirect(route('produits.show', $produit));

        $produit->refresh();
        $this->assertSame('Shampoing renommé', $produit->nom);
        $this->assertSame(10, $produit->quantite);
    }

    public function test_desactiver_puis_reactiver(): void
    {
        $produit = $this->produit();

        $this->actingAs($this->admin)->patch(route('produits.toggle', $produit))->assertRedirect();
        $this->assertFalse($produit->refresh()->is_active);

        // On cherche le lien vers la fiche (le message flash contient aussi le nom du produit).
        $this->get(route('produits.index'))->assertDontSee(route('produits.show', $produit));
        $this->get(route('produits.index', ['etat' => 'inactifs']))->assertSee(route('produits.show', $produit));

        $this->post(route('produits.mouvement', $produit), ['type' => 'entree', 'quantite' => 1])->assertSessionHasErrors('produit');
        $this->assertSame(10, $produit->refresh()->quantite);

        $this->patch(route('produits.toggle', $produit));
        $this->assertTrue($produit->refresh()->is_active);
    }

    // --- Mouvements ----------------------------------------------------------------

    public function test_entree_utilisation_vente_et_perte(): void
    {
        $produit = $this->produit(10);
        $this->actingAs($this->admin);

        $this->post(route('produits.mouvement', $produit), ['type' => 'entree', 'quantite' => 5, 'motif' => 'Facture 123'])->assertRedirect();
        $this->post(route('produits.mouvement', $produit), ['type' => 'utilisation', 'quantite' => 2])->assertRedirect();
        $this->post(route('produits.mouvement', $produit), ['type' => 'vente', 'quantite' => 3])->assertRedirect();
        $this->post(route('produits.mouvement', $produit), ['type' => 'perte', 'quantite' => 1, 'motif' => 'Flacon cassé'])->assertRedirect();

        $this->assertSame(9, $produit->refresh()->quantite);
        $this->assertSame(
            [10, 5, -2, -3, -1],
            $produit->mouvements()->orderBy('id')->pluck('quantite')->all(),
        );
        $this->assertSame(
            [10, 15, 13, 10, 9],
            $produit->mouvements()->orderBy('id')->pluck('quantite_apres')->all(),
        );
    }

    public function test_on_ne_sort_pas_plus_que_le_stock(): void
    {
        $produit = $this->produit(2);

        $this->actingAs($this->admin)
            ->post(route('produits.mouvement', $produit), ['type' => 'vente', 'quantite' => 3])
            ->assertSessionHasErrors('quantite');

        $this->assertSame(2, $produit->refresh()->quantite);
    }

    public function test_quantites_invalides_refusees(): void
    {
        $produit = $this->produit(5);
        $this->actingAs($this->admin);

        foreach (['0', '-2', '1.5', 'abc', ''] as $quantite) {
            $this->post(route('produits.mouvement', $produit), ['type' => 'entree', 'quantite' => $quantite])
                ->assertSessionHasErrors('quantite');
        }

        $this->post(route('produits.mouvement', $produit), ['type' => 'pirate', 'quantite' => 1])->assertSessionHasErrors('type');

        $this->assertSame(5, $produit->refresh()->quantite);
    }

    public function test_perte_et_inventaire_exigent_un_motif(): void
    {
        $produit = $this->produit(5);
        $this->actingAs($this->admin);

        $this->post(route('produits.mouvement', $produit), ['type' => 'perte', 'quantite' => 1])->assertSessionHasErrors('motif');
        $this->post(route('produits.mouvement', $produit), ['type' => 'inventaire', 'quantite' => 3])->assertSessionHasErrors('motif');

        $this->assertSame(5, $produit->refresh()->quantite);
    }

    public function test_inventaire_corrige_le_stock_a_la_quantite_comptee(): void
    {
        $produit = $this->produit(10);
        $this->actingAs($this->admin);

        $this->post(route('produits.mouvement', $produit), ['type' => 'inventaire', 'quantite' => 7, 'motif' => 'Comptage mensuel'])->assertRedirect();
        $this->assertSame(7, $produit->refresh()->quantite);
        $this->assertSame(-3, $produit->mouvements()->latest('id')->first()->quantite);

        $this->post(route('produits.mouvement', $produit), ['type' => 'inventaire', 'quantite' => 0, 'motif' => 'Tout est parti'])->assertRedirect();
        $this->assertSame(0, $produit->refresh()->quantite);

        $this->post(route('produits.mouvement', $produit), ['type' => 'inventaire', 'quantite' => 0, 'motif' => 'Rien à changer'])
            ->assertSessionHasErrors('quantite');
    }

    public function test_les_mouvements_ne_se_modifient_ni_ne_se_suppriment(): void
    {
        $produit = $this->produit(4);
        $mouvement = $produit->mouvements()->first();

        $mouvement->quantite = 400;
        $this->assertFalse($mouvement->save());
        $this->assertFalse($mouvement->delete());

        $this->assertSame(4, MouvementStock::find($mouvement->id)->quantite);
    }

    public function test_le_service_refuse_une_personne_sans_droit(): void
    {
        $produit = $this->produit(4);

        $this->expectException(AuthorizationException::class);
        $this->stock()->mouvement($produit, TypeMouvementStock::Vente, 1, null, User::factory()->receptionniste()->create());
    }

    public function test_chaque_mouvement_est_journalise(): void
    {
        $produit = $this->produit(4);
        $this->stock()->mouvement($produit, TypeMouvementStock::Vente, 1, null, $this->admin);

        $this->assertDatabaseHas('historique_actions', ['action' => 'stock.vente', 'sujet_id' => $produit->id]);
        $this->assertSame(1, HistoriqueAction::where('action', 'stock.entree')->count());
    }

    // --- Alertes et valeur ---------------------------------------------------------

    public function test_alerte_stock_bas_et_valeur_du_stock(): void
    {
        $this->produit(10, ['nom' => 'Bien fourni', 'seuil_alerte' => 3, 'prix_achat' => 1000]);
        $this->produit(3, ['nom' => 'Au seuil', 'seuil_alerte' => 3, 'prix_achat' => 2000]);
        $this->produit(0, ['nom' => 'En rupture', 'seuil_alerte' => 1, 'prix_achat' => 5000]);
        $this->produit(0, ['nom' => 'Sans prix', 'seuil_alerte' => 0, 'prix_achat' => null]);

        $this->assertSame(
            ['Au seuil', 'En rupture', 'Sans prix'],
            Produit::enAlerte()->orderBy('nom')->pluck('nom')->all(),
        );

        // 10 × 1 000 + 3 × 2 000
        $this->assertSame(16000, $this->stock()->valeurTotale());

        $this->actingAs($this->admin)
            ->get(route('produits.index', ['etat' => 'alerte']))
            ->assertOk()
            ->assertSee('Au seuil')
            ->assertSee('Rupture')
            ->assertDontSee('Bien fourni')
            ->assertSee(Fcfa::format(16000), false);
    }

    // --- Pages et données de démo --------------------------------------------------

    public function test_les_pages_s_affichent(): void
    {
        $produit = $this->produit(5);
        $this->actingAs($this->admin);

        $this->get(route('produits.index'))->assertOk()->assertSee('Shampoing kératine');
        $this->get(route('produits.create'))->assertOk();
        $this->get(route('produits.show', $produit))->assertOk()->assertSee('5 flacons')->assertSee('Nouveau mouvement');
        $this->get(route('produits.edit', $produit))->assertOk();
        $this->get(route('produits.index', ['q' => ['x'], 'etat' => ['y']]))->assertOk();
        $this->get(route('produits.show', 999999))->assertNotFound();
    }

    public function test_affichage_des_unites(): void
    {
        $produit = new Produit(['unite' => 'flacon']);
        $this->assertSame('1 flacon', $produit->quantiteFormatee(1));
        $this->assertSame('3 flacons', $produit->quantiteFormatee(3));

        $produit->unite = 'kg';
        $this->assertSame('3 kg', $produit->quantiteFormatee(3));

        $produit->unite = 'boîtes';
        $this->assertSame('2 boîtes', $produit->quantiteFormatee(2));
    }

    public function test_le_seeder_de_demo_est_rejouable(): void
    {
        $this->seed(DemoProduitsSeeder::class);
        $this->seed(DemoProduitsSeeder::class);

        $this->assertSame(8, Produit::count());
        $this->assertSame(9, Produit::where('reference', 'DEMO-01')->first()->quantite);
        $this->assertSame(3, Produit::enAlerte()->count());
    }

    public function test_le_service_rejette_une_quantite_initiale_negative(): void
    {
        $this->expectException(ValidationException::class);
        $this->produit(-1);
    }
}
