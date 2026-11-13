<?php

namespace Tests\Feature\Depenses;

use App\Models\Depense;
use App\Models\User;

class DepensePolicyTest extends DepenseTestCase
{
    public function test_l_administratrice_gere_les_depenses(): void
    {
        $depense = $this->creer();

        $this->assertTrue($this->admin->can('viewAny', Depense::class));
        $this->assertTrue($this->admin->can('view', $depense));
        $this->assertTrue($this->admin->can('create', Depense::class));
        $this->assertTrue($this->admin->can('update', $depense));
        $this->assertTrue($this->admin->can('annuler', $depense));
    }

    public function test_la_receptionniste_et_le_professionnel_n_ont_aucun_droit(): void
    {
        $depense = $this->creer();

        foreach ([$this->receptionniste, User::factory()->professionnel()->create()] as $user) {
            $this->assertFalse($user->can('viewAny', Depense::class));
            $this->assertFalse($user->can('view', $depense));
            $this->assertFalse($user->can('create', Depense::class));
            $this->assertFalse($user->can('update', $depense));
            $this->assertFalse($user->can('annuler', $depense));
        }
    }

    public function test_un_droit_individuel_donne_acces_sauf_aux_depenses_annulees(): void
    {
        $depense = $this->creer();
        $this->receptionniste->grantPermission('expenses.manage');

        $this->assertTrue($this->receptionniste->can('update', $depense));

        $this->depenses()->annuler($depense, 'Saisie en double', $this->admin);

        $this->assertFalse($this->receptionniste->can('update', $depense->fresh()));
        $this->assertFalse($this->receptionniste->can('annuler', $depense->fresh()));
        $this->assertTrue($this->receptionniste->can('view', $depense->fresh()));
    }

    public function test_un_compte_desactive_n_a_aucun_droit(): void
    {
        $inactif = User::factory()->administratrice()->inactif()->create();

        $this->assertFalse($inactif->can('viewAny', Depense::class));
        $this->assertFalse($inactif->can('create', Depense::class));
    }

    public function test_personne_ne_peut_supprimer_une_depense(): void
    {
        $this->assertFalse($this->receptionniste->can('delete', $this->creer()));
    }
}