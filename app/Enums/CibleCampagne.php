<?php

namespace App\Enums;

enum CibleCampagne: string
{
    case Toutes = 'toutes';
    case Absentes = 'absentes';
    case Nouvelles = 'nouvelles';

    public function label(): string
    {
        return match ($this) {
            self::Toutes => 'Toutes les clientes',
            self::Absentes => 'Clientes absentes depuis un moment',
            self::Nouvelles => 'Nouvelles clientes',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Toutes => 'Toutes les clientes actives qui acceptent les messages.',
            self::Absentes => 'Pas de rendez-vous ni de paiement depuis le nombre de jours choisi.',
            self::Nouvelles => 'Inscrites depuis le nombre de jours choisi.',
        };
    }

    public function utiliseJours(): bool
    {
        return $this !== self::Toutes;
    }
}
