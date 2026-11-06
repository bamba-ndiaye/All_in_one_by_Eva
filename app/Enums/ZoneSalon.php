<?php

namespace App\Enums;

enum ZoneSalon: string
{
    case Mixte = 'mixte';
    case Femmes = 'femmes';

    public function label(): string
    {
        return match ($this) {
            self::Mixte => 'Zone mixte',
            self::Femmes => 'Espace femmes',
        };
    }
}