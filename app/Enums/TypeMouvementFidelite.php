<?php

namespace App\Enums;

enum TypeMouvementFidelite: string
{
    case Gain = 'gain';
    case Retrait = 'retrait';
    case Utilisation = 'utilisation';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Gain => 'Points gagnés',
            self::Retrait => 'Paiement annulé',
            self::Utilisation => 'Récompense utilisée',
            self::Correction => 'Correction manuelle',
        };
    }
}
