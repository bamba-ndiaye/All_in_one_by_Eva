<?php

namespace Tests\Feature\Caisse;

use Illuminate\Support\Carbon;

class TotauxCaisseTest extends CaisseTestCase
{
    public function test_un_paiement_et_son_annulation_ne_comptent_pas_deux_fois(): void
    {
        $rdv1 = $this->rdv();
        $rdv2 = $this->rdv();

        $erreur = $this->payer($rdv1, 10000);                  // espèces, saisi par erreur
        $this->payer($rdv2, 5000, $this->wave);                // Wave
        $this->caisse()->annuler($erreur, 'Erreur de saisie', $this->admin);
        $this->payer($rdv1, 10000);                            // re-saisie correcte

        $this->assertSame(15000, $this->caisse()->totalEncaisse('2026-11-09', '2026-11-09'));

        $this->assertSame(
            [['mode' => 'Espèces', 'total' => 10000], ['mode' => 'Wave', 'total' => 5000]],
            $this->caisse()->parMode('2026-11-09', '2026-11-09')->values()->all(),
        );
    }

    public function test_un_paiement_totalement_annule_vaut_zero(): void
    {
        $paiement = $this->payer($this->rdv(), 10000);
        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertSame(0, $this->caisse()->totalEncaisse('2026-11-09', '2026-11-09'));
        $this->assertSame([['mode' => 'Espèces', 'total' => 0]], $this->caisse()->parMode('2026-11-09', '2026-11-09')->values()->all());
    }

    public function test_les_totaux_respectent_les_bornes_de_la_periode(): void
    {
        $rdv = $this->rdv();
        $this->payer($rdv, 4000, null, $this->admin, ['date' => '2026-11-02']);
        $this->payer($rdv, 6000);

        $this->assertSame(6000, $this->caisse()->totalEncaisse('2026-11-09', '2026-11-09'));
        $this->assertSame(4000, $this->caisse()->totalEncaisse('2026-11-02', '2026-11-08'));
        $this->assertSame(10000, $this->caisse()->totalEncaisse('2026-11-01', '2026-11-30'));
        $this->assertSame(10000, $this->caisse()->totalEncaisse(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')));
        $this->assertSame(0, $this->caisse()->totalEncaisse('2026-12-01', '2026-12-31'));
    }

    public function test_une_periode_sans_paiement_donne_zero_et_aucun_mode(): void
    {
        $this->assertSame(0, $this->caisse()->totalEncaisse('2026-11-01', '2026-11-30'));
        $this->assertCount(0, $this->caisse()->parMode('2026-11-01', '2026-11-30'));
    }
}