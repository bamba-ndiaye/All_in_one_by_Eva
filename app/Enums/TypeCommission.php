<?php

namespace App\Enums;

enum TypeCommission: string
{
    case Pourcentage = 'pourcentage';
    case Fixe = 'fixe';

    public function label(): string
    {
        return match ($this) {
            self::Pourcentage => 'Pourcentage du prix de la prestation',
            self::Fixe => 'Montant fixe par prestation (FCFA)',
        };
    }
}