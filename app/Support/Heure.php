<?php

namespace App\Support;

class Heure
{
    /** « 09:30 » ou « 09:30:00 » vers des minutes depuis minuit. */
    public static function toMinutes(string $time): int
    {
        [$heures, $minutes] = array_map('intval', explode(':', $time) + [0, 0]);

        return $heures * 60 + $minutes;
    }

    /** Minutes depuis minuit vers « 09:30 ». */
    public static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Format stocké en base : « 09:30:00 ». */
    public static function sql(int $minutes): string
    {
        return self::format($minutes).':00';
    }
}