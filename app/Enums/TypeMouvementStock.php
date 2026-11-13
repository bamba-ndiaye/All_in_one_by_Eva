<?php

namespace App\Enums;

enum TypeMouvementStock: string
{
    case Entree = 'entree';
    case Utilisation = 'utilisation';
    case Vente = 'vente';
    case Perte = 'perte';
    case Inventaire = 'inventaire';

    public function label(): string
    {
        return match ($this) {
            self::Entree => 'Entrée (réception)',
            self::Utilisation => 'Utilisation au salon',
            self::Vente => 'Vente à une cliente',
            self::Perte => 'Perte ou casse',
            self::Inventaire => 'Inventaire (comptage)',
        };
    }

    /** Le mouvement retire-t-il des unités du stock ? */
    public function estSortie(): bool
    {
        return in_array($this, [self::Utilisation, self::Vente, self::Perte], true);
    }

    /** Un motif est obligatoire pour les pertes et les corrections d'inventaire. */
    public function motifObligatoire(): bool
    {
        return in_array($this, [self::Perte, self::Inventaire], true);
    }
}
