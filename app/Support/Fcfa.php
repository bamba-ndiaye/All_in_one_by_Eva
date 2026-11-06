<?php

namespace App\Support;

class Fcfa
{
    public static function format(int $amount): string
    {
        return number_format($amount, 0, '', "\u{00A0}")."\u{00A0}FCFA";
    }
}