<?php

namespace App\Enums;

enum StatutRendezVous: string
{
    case EnAttente = 'en_attente';
    case Confirme = 'confirme';
    case ClientArrive = 'client_arrive';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Annule = 'annule';
    case ClientAbsent = 'client_absent';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de confirmation',
            self::Confirme => 'Confirmé',
            self::ClientArrive => 'Client arrivé',
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::Annule => 'Annulé',
            self::ClientAbsent => 'Client absent',
        };
    }

    /** Classes Tailwind du badge. */
    public function badge(): string
    {
        return match ($this) {
            self::EnAttente => 'bg-amber-50 text-amber-700',
            self::Confirme => 'bg-sky-50 text-sky-700',
            self::ClientArrive => 'bg-violet-50 text-violet-700',
            self::EnCours => 'bg-bordeaux-50 text-bordeaux-700',
            self::Termine => 'bg-emerald-50 text-emerald-700',
            self::Annule => 'bg-stone-100 text-stone-500',
            self::ClientAbsent => 'bg-red-50 text-red-700',
        };
    }

    /** @return list<self> Statuts atteignables depuis celui-ci. */
    public function transitions(): array
    {
        return match ($this) {
            self::EnAttente => [self::Confirme, self::Annule],
            self::Confirme => [self::ClientArrive, self::ClientAbsent, self::Annule],
            self::ClientArrive => [self::EnCours, self::Annule],
            self::EnCours => [self::Termine, self::Annule],
            self::Termine, self::Annule, self::ClientAbsent => [],
        };
    }

    public function peutPasserA(self $vers): bool
    {
        return in_array($vers, $this->transitions(), true);
    }

    /** Un rendez-vous annulé ou absent libère son créneau. */
    public function bloqueLeCreneau(): bool
    {
        return ! in_array($this, [self::Annule, self::ClientAbsent], true);
    }

    /** Seuls les rendez-vous pas encore commencés se modifient ou se déplacent. */
    public function estModifiable(): bool
    {
        return in_array($this, [self::EnAttente, self::Confirme], true);
    }

    /** Statuts qui constatent ce qui se passe dans le salon (jamais pour un jour futur). */
    public function estTerrain(): bool
    {
        return in_array($this, [self::ClientArrive, self::EnCours, self::Termine, self::ClientAbsent], true);
    }

    /** @return list<self> */
    public static function creables(): array
    {
        return [self::EnAttente, self::Confirme];
    }

    /** @return list<string> */
    public static function bloquantsValeurs(): array
    {
        return array_values(array_map(
            fn (self $statut) => $statut->value,
            array_filter(self::cases(), fn (self $statut) => $statut->bloqueLeCreneau()),
        ));
    }
}