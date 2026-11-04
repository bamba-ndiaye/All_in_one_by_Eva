<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case Administratrice = 'administratrice';
    case Receptionniste = 'receptionniste';
    case Professionnel = 'professionnel';

    public function label(): string
    {
        return match ($this) {
            self::Administratrice => 'Administratrice',
            self::Receptionniste => 'Réceptionniste',
            self::Professionnel => 'Professionnel(le)',
        };
    }

    /**
     * Permissions accordées par défaut à ce rôle.
     * L'administratrice les possède toutes.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this === self::Administratrice) {
            return array_keys(config('permissions.catalogue'));
        }

        return config('permissions.roles.'.$this->value, []);
    }
}