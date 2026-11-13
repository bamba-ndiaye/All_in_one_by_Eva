<?php

namespace Tests\Feature\Caisse;

use App\Enums\StatutRendezVous;
use App\Models\RendezVous;

class ImpayesTest extends CaisseTestCase
{
    private function ids(): array
    {
        return RendezVous::impayes()->pluck('id')->all();
    }

    public function test_seuls_les_rendez_vous_termines_et_non_soldes_sont_impayes(): void
    {
        $impaye = $this->rdv();
        $partiel = $this->rdv();
        $this->payer($partiel, 5000);
        $solde = $this->rdv();
        $this->payer($solde, 20000);

        $this->rdv(StatutRendezVous::Confirme);
        $this->rdv(StatutRendezVous::EnCours);
        $this->rdv(StatutRendezVous::Annule);
        $this->rdv(StatutRendezVous::Termine, ['prix_prevu' => 0]);

        $this->assertEqualsCanonicalizing([$impaye->id, $partiel->id], $this->ids());
    }

    public function test_annuler_le_paiement_d_un_rendez_vous_solde_le_rend_impaye(): void
    {
        $rdv = $this->rdv();
        $paiement = $this->payer($rdv, 20000);
        $this->assertSame([], $this->ids());

        $this->caisse()->annuler($paiement, 'Erreur de saisie', $this->admin);

        $this->assertSame([$rdv->id], $this->ids());
    }

    public function test_un_rendez_vous_paye_par_deux_modes_n_est_pas_impaye(): void
    {
        $rdv = $this->rdv();
        $this->payer($rdv, 12000);
        $this->payer($rdv, 8000, $this->wave);

        $this->assertSame([], $this->ids());
    }
}