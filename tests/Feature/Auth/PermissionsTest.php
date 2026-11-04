<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_administratrice_a_toutes_les_permissions(): void
    {
        $admin = User::factory()->administratrice()->create();

        foreach (array_keys(config('permissions.catalogue')) as $permission) {
            $this->assertTrue($admin->hasPermission($permission), $permission);
        }
    }

    public function test_la_receptionniste_peut_encaisser_mais_pas_corriger(): void
    {
        $user = User::factory()->receptionniste()->create();

        $this->assertTrue($user->hasPermission('payments.record'));
        $this->assertTrue($user->hasPermission('clients.create'));
        $this->assertFalse($user->hasPermission('payments.correct'));
        $this->assertFalse($user->hasPermission('finance.view_revenue'));
        $this->assertFalse($user->hasPermission('clients.export'));
        $this->assertFalse($user->hasPermission('services.manage'));
    }

    public function test_le_professionnel_ne_voit_que_son_travail(): void
    {
        $user = User::factory()->professionnel()->create();

        $this->assertTrue($user->hasPermission('appointments.view_own'));
        $this->assertTrue($user->hasPermission('appointments.update_status'));
        $this->assertFalse($user->hasPermission('clients.view'));
        $this->assertFalse($user->hasPermission('appointments.view'));
        $this->assertFalse($user->hasPermission('payments.view'));
        $this->assertFalse($user->hasPermission('staff.view_earnings'));
    }

    public function test_un_droit_individuel_s_ajoute_au_role(): void
    {
        $user = User::factory()->receptionniste()->create();
        $this->assertFalse($user->hasPermission('clients.export'));

        $user->grantPermission('clients.export');
        $this->assertTrue($user->hasPermission('clients.export'));

        $user->revokePermission('clients.export');
        $this->assertFalse($user->hasPermission('clients.export'));
    }

    public function test_accorder_deux_fois_le_meme_droit_ne_cree_pas_de_doublon(): void
    {
        $user = User::factory()->receptionniste()->create();

        $user->grantPermission('clients.export');
        $user->grantPermission('clients.export');

        $this->assertSame(1, $user->extraPermissions()->count());
    }

    public function test_une_permission_inconnue_est_refusee(): void
    {
        $user = User::factory()->receptionniste()->create();

        $this->expectException(InvalidArgumentException::class);
        $user->grantPermission('inexistante.permission');
    }

    public function test_un_utilisateur_inactif_n_a_aucun_droit(): void
    {
        $admin = User::factory()->administratrice()->inactif()->create();
        $receptionniste = User::factory()->receptionniste()->inactif()->create();

        $this->assertFalse($admin->hasPermission('clients.view'));
        $this->assertFalse($receptionniste->hasPermission('clients.view'));
        $this->assertFalse(Gate::forUser($admin)->allows('clients.view'));
    }

    public function test_les_gates_suivent_les_permissions(): void
    {
        $receptionniste = User::factory()->receptionniste()->create();

        $this->assertTrue(Gate::forUser($receptionniste)->allows('payments.record'));
        $this->assertTrue(Gate::forUser($receptionniste)->denies('payments.correct'));
    }

    public function test_les_droits_par_defaut_existent_tous_dans_le_catalogue(): void
    {
        $catalogue = array_keys(config('permissions.catalogue'));

        foreach (config('permissions.roles') as $role => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertContains($permission, $catalogue, "{$role} : {$permission}");
            }
        }
    }

    public function test_le_role_a_un_libelle(): void
    {
        $this->assertSame('Administratrice', RoleUtilisateur::from('administratrice')->label());
        $this->assertSame('Réceptionniste', RoleUtilisateur::Receptionniste->label());
    }

    public function test_un_nouvel_utilisateur_est_professionnel_par_defaut(): void
    {
        $user = User::factory()->create();

        $this->assertSame(RoleUtilisateur::Professionnel, $user->role);
        $this->assertTrue($user->is_active);
    }
}