<?php

namespace App\Services;

class PhoneNormalizer
{
    private const SENEGAL = '221';

    /**
     * Retourne le numéro au format international (+221771234567), ou null s'il est invalide.
     */
    public function normalize(?string $input): ?string
    {
        $input = trim((string) $input);

        // Chiffres, espaces, ( ) . - et un « + » uniquement au début.
        if ($input === '' || ! preg_match('/^\+?[\d\s().\-]+$/', $input)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input);

        if ($digits === '') {
            return null;
        }

        $international = str_starts_with($input, '+');

        if (! $international && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $international = true;
        }

        if ($international) {
            return str_starts_with($digits, self::SENEGAL)
                ? $this->senegal(substr($digits, 3))
                : $this->etranger($digits);
        }

        // 221 déjà présent sans « + » : on ne le double jamais.
        if (strlen($digits) === 12 && str_starts_with($digits, self::SENEGAL)) {
            return $this->senegal(substr($digits, 3));
        }

        if (strlen($digits) === 9) {
            return $this->senegal($digits);
        }

        return null;
    }

    public function isValid(?string $input): bool
    {
        return $this->normalize($input) !== null;
    }

    /** Affichage lisible : +221 77 123 45 67 */
    public function format(?string $e164): string
    {
        if ($e164 === null || $e164 === '') {
            return '';
        }

        if (preg_match('/^\+221(\d{2})(\d{3})(\d{2})(\d{2})$/', $e164, $m)) {
            return "+221 {$m[1]} {$m[2]} {$m[3]} {$m[4]}";
        }

        return $e164;
    }

    /** Numéro sans « + », comme l'attend https://wa.me/ */
    public function forWhatsApp(?string $e164): ?string
    {
        return ($e164 === null || $e164 === '') ? null : ltrim($e164, '+');
    }

    private function senegal(string $national): ?string
    {
        return preg_match('/^(?:70|75|76|77|78|33)\d{7}$/', $national)
            ? '+'.self::SENEGAL.$national
            : null;
    }

    private function etranger(string $digits): ?string
    {
        $length = strlen($digits);

        return ($length >= 8 && $length <= 15 && $digits[0] !== '0')
            ? '+'.$digits
            : null;
    }
}