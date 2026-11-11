<?php

namespace Tests\Feature\Caisse;

use App\Models\Paiement;
use App\Models\User;

class PaiementPolicyTest extends CaisseTestCase
{
    public function test_la_receptionniste_consulte_et_encaisse_sans_corriger(): void
    {
        $paiement = $this->payer($this->rdv(), 1000);
        $user = $this->receptionniste;

        $this->assertTrue($user->can('viewAny', Paiement::class));
        $this->assertTrue($user->can('view', $paiement));
        $this->assertTrue($user->can('create', Paiement::class));
        $this->assertFalse($user->can('annuler', $paiement));
    }

    public function test_le_professionnel_n_a_aucun_acces_a_la_caisse(): void
    {
        $paiement = $this->payer($this->rdv(), 1000);
        $user = User::factory()->professionnel()->create();

        $this->assertFalse($user->can('viewAny', Paiement::class));
        $this->assertFalse($user->can('view', $paiement));
        $this->assertFalse($user->can('create', Paiement::class));
        $this->assertFalse($user->can('annuler', $paiement));
    }

    public function test_un_compte_desactive_n_a_aucun_droit(): void
    {
        $paiement = $this->payer($this->rdv(), 1000);
        $user = User::factory()->receptionniste()->inactif()->create();

        $this->assertFalse($user->can('viewAny', Paiement::class));
        $this->assertFalse($user->can('create', Paiement::class));
        $this->assertFalse($user->can('annuler', $paiement));
    }

    public function test_un_droit_individuel_permet_d_annuler_seulement_un_paiement_non_annule(): void
    {
        $paiement = $this->payer($this->rdv(), 1000);
        $user = $this->receptionniste;
        $user->grantPermission('payments.correct');

        $this->assertTrue($user->can('annuler', $paiement->fresh()));

        $annulation = $this->caisse()->annuler($paiement, 'Erreur de saisie', $user);

        $this->assertFalse($user->can('annuler', $paiement->fresh()));      // déjà annulé
        $this->assertFalse($user->can('annuler', $annulation->fresh()));    // une annulation ne s'annule pas
    }

    public function test_les_roles_ont_les_bonnes_permissions_de_caisse(): void
    {
        $this->assertTrue($this->receptionniste->hasPermission('payments.record'));
        $this->assertFalse($this->receptionniste->hasPermission('payments.correct'));
        $this->assertFalse($this->receptionniste->hasPermission('finance.view_revenue'));

        $this->assertTrue($this->admin->hasPermission('payments.correct'));
        $this->assertTrue($this->admin->hasPermission('finance.view_revenue'));
    }
}