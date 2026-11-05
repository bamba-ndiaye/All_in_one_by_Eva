<?php

namespace Tests\Feature\Catalogue;

use App\Models\Forfait;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CataloguePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_administratrice_gere_le_catalogue(): void
    {
        $admin = User::factory()->administratrice()->create();
        $service = Service::factory()->create();
        $forfait = Forfait::factory()->create();

        $this->assertTrue($admin->can('viewAny', Service::class));
        $this->assertTrue($admin->can('create', Service::class));
        $this->assertTrue($admin->can('update', $service));
        $this->assertTrue($admin->can('viewAny', Forfait::class));
        $this->assertTrue($admin->can('create', Forfait::class));
        $this->assertTrue($admin->can('update', $forfait));
    }

    public function test_la_receptionniste_consulte_sans_modifier(): void
    {
        $user = User::factory()->receptionniste()->create();
        $service = Service::factory()->create();
        $forfait = Forfait::factory()->create();

        $this->assertTrue($user->can('viewAny', Service::class));
        $this->assertTrue($user->can('view', $service));
        $this->assertFalse($user->can('create', Service::class));
        $this->assertFalse($user->can('update', $service));
        $this->assertTrue($user->can('view', $forfait));
        $this->assertFalse($user->can('create', Forfait::class));
        $this->assertFalse($user->can('update', $forfait));
    }

    public function test_le_professionnel_consulte_sans_modifier(): void
    {
        $user = User::factory()->professionnel()->create();
        $service = Service::factory()->create();

        $this->assertTrue($user->can('viewAny', Service::class));
        $this->assertFalse($user->can('update', $service));
        $this->assertFalse($user->can('create', Forfait::class));
    }

    public function test_un_compte_desactive_n_a_aucun_acces(): void
    {
        $admin = User::factory()->administratrice()->inactif()->create();
        $service = Service::factory()->create();

        $this->assertFalse($admin->can('viewAny', Service::class));
        $this->assertFalse($admin->can('update', $service));
    }

    public function test_un_droit_individuel_permet_de_gerer_le_catalogue(): void
    {
        $user = User::factory()->receptionniste()->create();
        $service = Service::factory()->create();
        $this->assertFalse($user->can('update', $service));

        $user->grantPermission('services.manage');

        $this->assertTrue($user->can('update', $service));
    }

    public function test_personne_ne_peut_supprimer_un_service_sauf_l_administratrice_via_gate(): void
    {
        $user = User::factory()->receptionniste()->create();
        $service = Service::factory()->create();

        $this->assertFalse($user->can('delete', $service));
    }
}