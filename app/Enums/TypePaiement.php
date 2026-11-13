<?php

namespace App\Enums;

enum TypePaiement: string
{
    case Paiement = 'paiement';
    case Annulation = 'annulation';

    public function label(): string
    {
        return match ($this) {
            self::Paiement => 'Paiement',
            self::Annulation => 'Annulation',
        };
    }

    /** Classes Tailwind du badge. */
    public function badge(): string
    {
        return match ($this) {
            self::Paiement => 'bg-emerald-50 text-emerald-700',
            self::Annulation => 'bg-red-50 text-red-700',
        };
    }
}