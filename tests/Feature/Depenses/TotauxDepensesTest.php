<?php

namespace Tests\Feature\Depenses;

use App\Enums\TypePaiement;
use App\Models\Client;
use App\Models\ModePaiement;
use App\Models\Paiement;
use Illuminate\Support\Carbon;

class TotauxDepensesTest extends DepenseTestCase
{
    private function encaisser(int $montant, string $date = '2026-11-09'): void
    {
        $client = Client::factory()->create();
        $mode = ModePaiement::factory()->create();

        Paiement::create([
            'client_id' => $client->id,
            'type' => TypePaiement::Paiement,
            'mode_paiement_id' => $mode->id,
            'montant' => $montant,
            'montant_total' => $montant,
            'reste_apres' => 0,
            'date' => $date,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_les_depenses_annulees_ne_comptent_pas(): void
    {
        $this->creer(['montant' => 10000]);
        $annulee = $this->creer(['montant' => 99000]);
        $this->depenses()->annuler($annulee, 'Saisie en double', $this->admin);

        $this->assertSame(10000, $this->depenses()->total('2026-11-01', '2026-11-30'));
    }

    public function test_les_totaux_respectent_les_bornes_de_la_periode(): void
    {
        $this->creer(['montant' => 4000, 'date' => '2026-11-02']);
        $this->creer(['montant' => 6000, 'date' => '2026-11-09']);

        $this->assertSame(6000, $this->depenses()->total('2026-11-09', '2026-11-09'));
        $this->assertSame(4000, $this->depenses()->total('2026-11-02', '2026-11-08'));
        $this->assertSame(10000, $this->depenses()->total('2026-11-01', '2026-11-30'));
        $this->assertSame(10000, $this->depenses()->total(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')));
        $this->assertSame(0, $this->depenses()->total('2026-12-01', '2026-12-31'));
    }

    public function test_le_total_par_categorie_est_ordonne_et_exclut_les_annulations(): void
    {
        $this->creer(['categorie_depense_id' => $this->produits->id, 'montant' => 25000]);
        $this->creer(['categorie_depense_id' => $this->produits->id, 'montant' => 5000]);
        $this->creer(['categorie_depense_id' => $this->loyer->id, 'montant' => 150000]);
        $annulee = $this->creer(['categorie_depense_id' => $this->loyer->id, 'montant' => 77000]);
        $this->depenses()->annuler($annulee, 'Saisie en double', $this->admin);

        $this->assertSame(
            [['categorie' => 'Loyer', 'total' => 150000], ['categorie' => 'Achat de produits', 'total' => 30000]],
            $this->depenses()->parCategorie('2026-11-01', '2026-11-30')->values()->all(),
        );
    }

    public function test_une_periode_sans_depense_donne_zero_et_aucune_categorie(): void
    {
        $this->assertSame(0, $this->depenses()->total('2026-11-01', '2026-11-30'));
        $this->assertCount(0, $this->depenses()->parCategorie('2026-11-01', '2026-11-30'));
    }

    public function test_le_resultat_estime_est_les_recettes_moins_les_depenses(): void
    {
        $this->encaisser(200000);
        $this->encaisser(50000, '2026-10-20');            // hors période
        $this->creer(['montant' => 150000]);
        $this->creer(['montant' => 30000, 'date' => '2026-10-20']);   // hors période

        $this->assertSame(
            ['encaisse' => 200000, 'depenses' => 150000, 'resultat' => 50000],
            $this->depenses()->resultatEstime('2026-11-01', '2026-11-30'),
        );
    }

    public function test_le_resultat_estime_peut_etre_negatif(): void
    {
        $this->encaisser(40000);
        $this->creer(['montant' => 150000]);

        $this->assertSame(-110000, $this->depenses()->resultatEstime('2026-11-01', '2026-11-30')['resultat']);
    }

    public function test_une_depense_annulee_ne_reduit_pas_le_resultat(): void
    {
        $this->encaisser(100000);
        $annulee = $this->creer(['montant' => 99000]);
        $this->depenses()->annuler($annulee, 'Saisie en double', $this->admin);

        $this->assertSame(100000, $this->depenses()->resultatEstime('2026-11-01', '2026-11-30')['resultat']);
    }
}