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

    /** Ce que ce rôle permet, en une phrase. */
    public function description(): string
    {
        return match ($this) {
            self::Administratrice => 'Accès complet : finances, équipe, paramètres et comptes.',
            self::Receptionniste => 'Accueil : clients, rendez-vous et encaissements.',
            self::Professionnel => 'Prestations : voit son propre planning et met à jour ses rendez-vous.',
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