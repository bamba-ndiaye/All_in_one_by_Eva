<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private function configure(?string $email, ?string $password): void
    {
        config(['salon.admin' => ['name' => 'Eva', 'email' => $email, 'password' => $password]]);
    }

    public function test_cree_l_administratrice(): void
    {
        $this->configure('Eva@Example.test', 'mot-de-passe-solide-123');

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'eva@example.test')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('mot-de-passe-solide-123', $admin->password));
    }

    public function test_ne_cree_rien_sans_configuration(): void
    {
        $this->configure(null, null);

        $this->seed(AdminSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_refuse_un_mot_de_passe_trop_court(): void
    {
        $this->configure('eva@example.test', 'court');

        $this->seed(AdminSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_ne_modifie_pas_un_compte_existant(): void
    {
        $existant = User::factory()->create(['email' => 'eva@example.test']);
        $this->configure('eva@example.test', 'autre-mot-de-passe-123');

        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('password', $existant->fresh()->password));
        $this->assertFalse($existant->fresh()->isAdmin());
    }
}