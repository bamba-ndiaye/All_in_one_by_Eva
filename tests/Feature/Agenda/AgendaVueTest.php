<?php

namespace Tests\Feature\Agenda;

use App\Support\AgendaVue;

class AgendaVueTest extends AgendaTestCase
{
    public function test_la_vue_jour(): void
    {
        $vue = AgendaVue::depuisRequete('jour', '2026-11-11');

        $this->assertSame('2026-11-11', $vue->debut()->toDateString());
        $this->assertSame('2026-11-11', $vue->fin()->toDateString());
        $this->assertSame('2026-11-10', $vue->precedent()->toDateString());
        $this->assertSame('2026-11-12', $vue->suivant()->toDateString());
        $this->assertCount(1, $vue->jours());
    }

    public function test_la_vue_semaine_va_du_lundi_au_dimanche(): void
    {
        // Mercredi 11 novembre 2026.
        $vue = AgendaVue::depuisRequete('semaine', '2026-11-11');

        $this->assertSame('2026-11-09', $vue->debut()->toDateString());
        $this->assertSame('2026-11-15', $vue->fin()->toDateString());
        $this->assertSame('2026-11-04', $vue->precedent()->toDateString());
        $this->assertSame('2026-11-18', $vue->suivant()->toDateString());
        $this->assertCount(7, $vue->jours());
    }

    public function test_la_vue_mois_et_sa_grille(): void
    {
        $vue = AgendaVue::depuisRequete('mois', '2026-11-11');

        $this->assertSame('2026-11-01', $vue->debut()->toDateString());
        $this->assertSame('2026-11-30', $vue->fin()->toDateString());
        $this->assertCount(30, $vue->jours());

        // Le 1er novembre 2026 est un dimanche, le 30 un lundi : six semaines de lundi à dimanche.
        $semaines = $vue->semaines();
        $this->assertCount(6, $semaines);
        $this->assertSame('2026-10-26', $semaines[0][0]->toDateString());
        $this->assertSame('2026-12-06', $semaines[5][6]->toDateString());

        foreach ($semaines as $semaine) {
            $this->assertCount(7, $semaine);
        }
    }

    public function test_la_navigation_par_mois_ne_deborde_pas(): void
    {
        $this->assertSame('2026-02-28', AgendaVue::depuisRequete('mois', '2026-03-31')->precedent()->toDateString());
        $this->assertSame('2026-02-28', AgendaVue::depuisRequete('mois', '2026-01-31')->suivant()->toDateString());
    }

    public function test_une_saisie_invalide_retombe_sur_aujourd_hui(): void
    {
        foreach ([['bidon', '2026-02-31'], [['x'], ['y']], [null, null], ['jour', 'pas-une-date']] as [$vue, $date]) {
            $periode = AgendaVue::depuisRequete($vue, $date);

            $this->assertSame('jour', $periode->vue);
            $this->assertSame('2026-11-09', $periode->date->toDateString());
        }
    }

    public function test_le_titre_contient_l_annee(): void
    {
        foreach (['jour', 'semaine', 'mois'] as $vue) {
            $this->assertStringContainsString('2026', AgendaVue::depuisRequete($vue, '2026-11-11')->titre());
        }
    }
}