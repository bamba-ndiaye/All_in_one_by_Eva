<?php

namespace Tests\Unit;

use App\Enums\StatutRendezVous as S;
use PHPUnit\Framework\TestCase;

class StatutRendezVousTest extends TestCase
{
    public function test_les_statuts_definitifs_n_ont_aucune_sortie(): void
    {
        foreach ([S::Termine, S::Annule, S::ClientAbsent] as $statut) {
            $this->assertSame([], $statut->transitions(), $statut->value);
        }
    }

    public function test_les_passages_autorises(): void
    {
        $this->assertTrue(S::EnAttente->peutPasserA(S::Confirme));
        $this->assertTrue(S::Confirme->peutPasserA(S::ClientArrive));
        $this->assertTrue(S::Confirme->peutPasserA(S::ClientAbsent));
        $this->assertTrue(S::ClientArrive->peutPasserA(S::EnCours));
        $this->assertTrue(S::EnCours->peutPasserA(S::Termine));
        $this->assertTrue(S::EnCours->peutPasserA(S::Annule));
    }

    public function test_les_passages_interdits(): void
    {
        $this->assertFalse(S::EnAttente->peutPasserA(S::ClientArrive));
        $this->assertFalse(S::Confirme->peutPasserA(S::EnAttente));
        $this->assertFalse(S::Confirme->peutPasserA(S::Termine));
        $this->assertFalse(S::Termine->peutPasserA(S::Confirme));
        $this->assertFalse(S::Annule->peutPasserA(S::Confirme));
        $this->assertFalse(S::ClientArrive->peutPasserA(S::ClientArrive));
    }

    public function test_les_creneaux_bloques_et_modifiables(): void
    {
        $this->assertFalse(S::Annule->bloqueLeCreneau());
        $this->assertFalse(S::ClientAbsent->bloqueLeCreneau());
        $this->assertTrue(S::Termine->bloqueLeCreneau());
        $this->assertCount(5, S::bloquantsValeurs());
        $this->assertNotContains('annule', S::bloquantsValeurs());

        $this->assertTrue(S::EnAttente->estModifiable());
        $this->assertTrue(S::Confirme->estModifiable());
        $this->assertFalse(S::EnCours->estModifiable());
        $this->assertFalse(S::Termine->estModifiable());
    }

    public function test_statuts_terrain_et_creables(): void
    {
        $this->assertTrue(S::Termine->estTerrain());
        $this->assertTrue(S::ClientAbsent->estTerrain());
        $this->assertFalse(S::Confirme->estTerrain());
        $this->assertFalse(S::Annule->estTerrain());
        $this->assertSame([S::EnAttente, S::Confirme], S::creables());
    }
}