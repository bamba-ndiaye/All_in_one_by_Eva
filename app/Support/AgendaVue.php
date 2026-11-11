<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AgendaVue
{
    public const JOUR = 'jour';
    public const SEMAINE = 'semaine';
    public const MOIS = 'mois';

    public function __construct(public readonly string $vue, public readonly Carbon $date)
    {
    }

    public static function depuisRequete(mixed $vue, mixed $date): self
    {
        $vue = is_string($vue) && in_array($vue, [self::JOUR, self::SEMAINE, self::MOIS], true)
            ? $vue
            : self::JOUR;

        return new self($vue, self::lireDate($date) ?? now()->startOfDay());
    }

    /** Date « AAAA-MM-JJ » réelle, ou null (« 2026-02-31 » est refusée). */
    public static function lireDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return ($date && $date->format('Y-m-d') === $value) ? $date : null;
    }

    public function debut(): Carbon
    {
        return match ($this->vue) {
            self::SEMAINE => $this->date->copy()->startOfWeek(CarbonInterface::MONDAY),
            self::MOIS => $this->date->copy()->startOfMonth(),
            default => $this->date->copy(),
        };
    }

    public function fin(): Carbon
    {
        return match ($this->vue) {
            self::SEMAINE => $this->debut()->addDays(6),
            self::MOIS => $this->date->copy()->endOfMonth()->startOfDay(),
            default => $this->date->copy(),
        };
    }

    public function precedent(): Carbon
    {
        return match ($this->vue) {
            self::SEMAINE => $this->date->copy()->subWeek(),
            self::MOIS => $this->date->copy()->subMonthNoOverflow(),
            default => $this->date->copy()->subDay(),
        };
    }

    public function suivant(): Carbon
    {
        return match ($this->vue) {
            self::SEMAINE => $this->date->copy()->addWeek(),
            self::MOIS => $this->date->copy()->addMonthNoOverflow(),
            default => $this->date->copy()->addDay(),
        };
    }

    public function titre(): string
    {
        return match ($this->vue) {
            self::SEMAINE => 'Semaine du '.$this->debut()->translatedFormat('j F').' au '.$this->fin()->translatedFormat('j F Y'),
            self::MOIS => ucfirst($this->date->translatedFormat('F Y')),
            default => ucfirst($this->date->translatedFormat('l j F Y')),
        };
    }

    /** @return list<Carbon> Chaque jour de la période. */
    public function jours(): array
    {
        $jours = [];
        $fin = $this->fin();

        for ($jour = $this->debut(); $jour->lte($fin); $jour = $jour->copy()->addDay()) {
            $jours[] = $jour;
        }

        return $jours;
    }

    /** @return list<list<Carbon>> Grille du mois : semaines de lundi à dimanche. */
    public function semaines(): array
    {
        $jour = $this->debut()->startOfWeek(CarbonInterface::MONDAY);
        $dernier = $this->fin()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
        $semaines = [];

        while ($jour->lte($dernier)) {
            $semaine = [];

            for ($i = 0; $i < 7; $i++) {
                $semaine[] = $jour->copy();
                $jour->addDay();
            }

            $semaines[] = $semaine;
        }

        return $semaines;
    }
}