<?php

namespace App\Enums;

enum JourSemaine: int
{
    case Lundi = 1;
    case Mardi = 2;
    case Mercredi = 3;
    case Jeudi = 4;
    case Vendredi = 5;
    case Samedi = 6;
    case Dimanche = 7;

    public function label(): string
    {
        return match ($this) {
            self::Lundi => 'Lundi',
            self::Mardi => 'Mardi',
            self::Mercredi => 'Mercredi',
            self::Jeudi => 'Jeudi',
            self::Vendredi => 'Vendredi',
            self::Samedi => 'Samedi',
            self::Dimanche => 'Dimanche',
        };
    }

    public function initiale(): string
    {
        return mb_strtoupper(mb_substr($this->label(), 0, 1));
    }
}