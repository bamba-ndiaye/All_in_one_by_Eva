<?php

namespace Tests\Feature\Rapports;

use App\Enums\StatutRendezVous;
use App\Enums\TypeCommission;
use App\Models\CategorieDepense;
use App\Models\Client;
use App\Models\Depense;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Services\RapportService;
use App\Support\Fcfa;
use Carbon\CarbonImmutable;
use Tests\Feature\Caisse\CaisseTestCase;

/**
 * Date du jour figée par AgendaTestCase : lundi 9 novembre 2026.
 */
class RapportTest extends CaisseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->employe->update(['commission_type' => TypeCommission::Pourcentage, 'commission_valeur' => 10]);
    }

    /** Rendez-vous avec ses lignes de prestations (prix figés). */
    private function rdvAvec(string $date, StatutRendezVous $statut, array $services): RendezVous
    {
        $rdv = RendezVous::factory()->create([
            'employe_id' => $this->employe->id,
            'client_id' => $this->client->id,
            'date' => $date,
            'statut' => $statut,
            'prix_prevu' => array_sum(array_map(fn (Service $s) => $s->prix, $services)),
        ]);

        foreach ($services as $s) {
            $rdv->lignes()->create(['service_id' => $s->id, 'nom' => $s->nom, 'prix_applique' => $s->prix, 'duree_appliquee' => $s->duree_minutes]);
        }

        return $rdv;
    }

    private function service(): RapportService
    {
        return app(RapportService::class);
    }

    private function nov(): array
    {
        return [CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-09')];
    }

    public function test_finances_encaisse_depenses_resultat_et_panier_moyen(): void
    {
        $rdv = $this->rdvAvec('2026-11-05', StatutRendezVous::Termine, [$this->massage]);
        $this->payer($rdv, 10000);
        $annule = $this->payer($rdv, 5000);
        $this->caisse()->annuler($annule, 'Erreur de saisie', $this->admin);

        Depense::factory()->create(['categorie_depense_id' => CategorieDepense::factory()->create()->id, 'montant' => 4000, 'date' => '2026-11-03']);

        $f = $this->service()->finances(...$this->nov());

        $this->assertSame(10000, $f['encaisse']);
        $this->assertSame(4000, $f['depenses']);
        $this->assertSame(6000, $f['resultat']);
        $this->assertSame(1, $f['nb_paiements']);
        $this->assertSame(10000, $f['panier_moyen']);
        $this->assertNull($f['evolution']);
    }

    public function test_comparaison_avec_les_memes_jours_du_mois_precedent(): void
    {
        [$debut, $fin] = $this->service()->periodePrecedente(...$this->nov());
        $this->assertSame(['2026-10-01', '2026-10-09'], [$debut->toDateString(), $fin->toDateString()]);

        // 31 mars → 28 février (pas de débordement en mars).
        [$d, $f] = $this->service()->periodePrecedente(CarbonImmutable::parse('2027-03-01'), CarbonImmutable::parse('2027-03-31'));
        $this->assertSame(['2027-02-01', '2027-02-28'], [$d->toDateString(), $f->toDateString()]);

        // Période libre : même durée juste avant.
        [$d, $f] = $this->service()->periodePrecedente(CarbonImmutable::parse('2026-11-05'), CarbonImmutable::parse('2026-11-14'));
        $this->assertSame(['2026-10-26', '2026-11-04'], [$d->toDateString(), $f->toDateString()]);
    }

    public function test_evolution_en_pourcentage(): void
    {
        $this->travelTo('2026-10-05 10:00:00');
        $this->payer($this->rdvAvec('2026-10-05', StatutRendezVous::Termine, [$this->massage]), 10000);
        $this->travelTo('2026-11-09 10:00:00');
        $this->payer($this->rdvAvec('2026-11-05', StatutRendezVous::Termine, [$this->massage]), 15000);

        $this->assertSame(50, $this->service()->finances(...$this->nov())['evolution']);
    }

    public function test_activite_taux_et_nouvelles_clientes(): void
    {
        $this->rdvAvec('2026-11-02', StatutRendezVous::Termine, [$this->coupe]);
        $this->rdvAvec('2026-11-03', StatutRendezVous::Termine, [$this->coupe]);
        $this->rdvAvec('2026-11-04', StatutRendezVous::Annule, [$this->coupe]);
        $this->rdvAvec('2026-11-06', StatutRendezVous::ClientAbsent, [$this->coupe]);
        $this->rdvAvec('2026-10-20', StatutRendezVous::Termine, [$this->coupe]); // hors période

        $nouvelle = Client::factory()->create();
        $nouvelle->forceFill(['created_at' => '2026-11-04 12:00:00'])->save();

        $a = $this->service()->activite(...$this->nov());

        $this->assertSame(4, $a['total']);
        $this->assertSame(2, $a['termines']);
        $this->assertSame(25, $a['taux_annulation']);
        $this->assertSame(25, $a['taux_absence']);
        $this->assertGreaterThanOrEqual(1, $a['nouvelles_clientes']);
    }

    public function test_prestations_et_equipe_seulement_sur_les_rendez_vous_termines(): void
    {
        $this->rdvAvec('2026-11-02', StatutRendezVous::Termine, [$this->coupe, $this->massage]);
        $this->rdvAvec('2026-11-03', StatutRendezVous::Termine, [$this->coupe]);
        $this->rdvAvec('2026-11-04', StatutRendezVous::Annule, [$this->massage]);

        $prestations = $this->service()->prestations(...$this->nov());
        $this->assertSame(
            [[$this->coupe->nom, 2, 10000], [$this->massage->nom, 1, 15000]],
            $prestations->map(fn ($p) => [$p['nom'], $p['nombre'], $p['montant']])->all(),
        );

        $equipe = $this->service()->equipe(...$this->nov());
        $this->assertCount(1, $equipe);
        $this->assertSame(2, $equipe[0]['rdv']);
        $this->assertSame(3, $equipe[0]['prestations']);
        $this->assertSame(25000, $equipe[0]['montant']);
        $this->assertSame(2500, $equipe[0]['commission']);

        $this->employe->update(['commission_type' => TypeCommission::Fixe, 'commission_valeur' => 1000]);
        $this->assertSame(3000, $this->service()->equipe(...$this->nov())[0]['commission']);
    }

    // --- Pages et droits -----------------------------------------------------------

    public function test_l_administratrice_voit_tout(): void
    {
        $rdv = $this->rdvAvec('2026-11-05', StatutRendezVous::Termine, [$this->massage]);
        $this->payer($rdv, 15000);

        $this->actingAs($this->admin)
            ->get(route('rapports.index'))
            ->assertOk()
            ->assertSee('Encaissé')
            ->assertSee(Fcfa::format(15000), false)
            ->assertSee($this->massage->nom)
            ->assertSee('Commission estimée')
            ->assertSee('Exporter');
    }

    public function test_sans_droit_finances_les_montants_sont_caches(): void
    {
        $this->payer($this->rdvAvec('2026-11-05', StatutRendezVous::Termine, [$this->massage]), 15000);

        $user = User::factory()->receptionniste()->create();
        $user->grantPermission('reports.view');

        $this->actingAs($user)
            ->get(route('rapports.index'))
            ->assertOk()
            ->assertSee('Rendez-vous')
            ->assertDontSee('Résultat estimé')
            ->assertDontSee('Commission estimée')
            ->assertDontSee('Exporter');

        $this->get(route('rapports.export'))->assertForbidden();
    }

    public function test_la_receptionniste_sans_droit_n_a_pas_acces(): void
    {
        $this->actingAs($this->receptionniste)->get(route('rapports.index'))->assertForbidden();
    }

    public function test_periodes_et_parametres_inattendus(): void
    {
        $this->actingAs($this->admin);

        foreach (['mois', 'mois-dernier', '30-jours', 'annee', 'pirate'] as $p) {
            $this->get(route('rapports.index', ['periode' => $p]))->assertOk();
        }

        $this->get(route('rapports.index', ['periode' => 'mois-dernier']))->assertSee('Du 1 octobre 2026 au 31 octobre 2026');
        $this->get(route('rapports.index', ['periode' => 'libre', 'du' => '2026-11-05', 'au' => '2026-11-01']))
            ->assertOk()->assertSee('Du 1 novembre 2026 au 5 novembre 2026');
        $this->get(route('rapports.index', ['periode' => ['x'], 'du' => ['y'], 'au' => '2026-02-31']))->assertOk();
    }

    public function test_export_csv(): void
    {
        $this->payer($this->rdvAvec('2026-11-05', StatutRendezVous::Termine, [$this->massage]), 15000);

        $reponse = $this->actingAs($this->admin)->get(route('rapports.export', ['periode' => 'mois']));
        $reponse->assertOk();
        $this->assertStringContainsString('rapport-2026-11-01-au-2026-11-09.csv', $reponse->headers->get('Content-Disposition'));

        $csv = $reponse->streamedContent();
        $this->assertStringContainsString('Encaissé;15000', $csv);
        // Les noms avec espaces sont entre guillemets, comme le veut le format CSV.
        $this->assertMatchesRegularExpression('/"?'.preg_quote($this->massage->nom, '/').'"?;1;15000/', $csv);
    }
}
