<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Employe;
use App\Models\Produit;
use App\Models\RendezVous;
use App\Models\User;
use Database\Seeders\JeuDeTestSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Agenda\AgendaTestCase;

class JeuDeTestSeederTest extends AgendaTestCase
{
    public function test_le_jeu_de_test_cree_les_donnees_et_les_comptes_et_reste_repetable(): void
    {
        $this->seed(JeuDeTestSeeder::class);
        $this->seed(JeuDeTestSeeder::class);

        $reception = User::where('email', 'reception@test.local')->firstOrFail();
        $pro = User::where('email', 'awa.fall@test.local')->firstOrFail();

        $this->assertSame(RoleUtilisateur::Receptionniste, $reception->role);
        $this->assertSame(RoleUtilisateur::Professionnel, $pro->role);
        $this->assertTrue(Hash::check('Reception-2026', $reception->password));
        $this->assertTrue(Hash::check('Coiffeuse-2026', $pro->password));
        $this->assertSame($pro->id, Employe::where('nom', '[Démo] Fall')->value('user_id'));

        $this->assertSame(8, Client::where('nom', 'like', '[Démo]%')->count());
        $this->assertSame(8, RendezVous::where('commentaire', '[Démo]')->count());
        $this->assertSame(8, Produit::where('reference', 'like', 'DEMO-%')->count());
        $this->assertSame(1, User::where('email', 'reception@test.local')->count());

        $this->post(route('login.store'), ['email' => 'reception@test.local', 'password' => 'Reception-2026'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_le_jeu_de_test_est_refuse_en_production(): void
    {
        $this->app['env'] = 'production';

        $this->seed(JeuDeTestSeeder::class);

        $this->assertNull(User::where('email', 'reception@test.local')->first());
        $this->assertSame(0, Client::where('nom', 'like', '[Démo]%')->count());
    }
}
