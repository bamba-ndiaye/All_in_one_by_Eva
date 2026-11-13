<?php

namespace App\Enums;

enum StatutCampagne: string
{
    case Brouillon = 'brouillon';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnCours => 'En cours d\'envoi',
            self::Terminee => 'Terminée',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Brouillon => 'bg-stone-100 text-stone-600',
            self::EnCours => 'bg-amber-50 text-amber-700',
            self::Terminee => 'bg-emerald-50 text-emerald-700',
        };
    }
}
