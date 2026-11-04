<?php

namespace Tests\Feature;

use App\Models\HistoriqueAction;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_action_est_enregistree_avec_son_auteur_et_son_sujet(): void
    {
        $admin = User::factory()->administratrice()->create();
        $cible = User::factory()->create();

        $this->actingAs($admin);

        $log = app(ActivityLogger::class)->log('utilisateur.modifie', $cible, 'Test');

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('utilisateur.modifie', $log->action);
        $this->assertSame($cible->id, $log->sujet_id);
        $this->assertSame($cible->getMorphClass(), $log->sujet_type);
    }

    public function test_les_mots_de_passe_ne_sont_jamais_journalises(): void
    {
        $log = app(ActivityLogger::class)->log(
            'utilisateur.cree',
            null,
            null,
            ['password' => 'secret', 'name' => 'Awa'],
            ['password' => 'secret2'],
        );

        $this->assertSame('[masqué]', $log->fresh()->anciennes_valeurs['password']);
        $this->assertSame('Awa', $log->fresh()->anciennes_valeurs['name']);
        $this->assertSame('[masqué]', $log->fresh()->nouvelles_valeurs['password']);
    }

    public function test_le_journal_ne_peut_etre_ni_modifie_ni_supprime(): void
    {
        $log = app(ActivityLogger::class)->log('test.action');

        $this->assertFalse($log->update(['action' => 'autre.action']));
        $this->assertFalse($log->delete());

        $this->assertSame('test.action', HistoriqueAction::find($log->id)->action);
    }
}