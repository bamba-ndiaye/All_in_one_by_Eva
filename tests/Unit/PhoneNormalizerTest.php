<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_les_numeros_senegalais_sont_normalises_sans_prefixe_en_double(): void
    {
        $normalizer = new PhoneNormalizer();

        $cas = [
            '77 123 45 67' => '+221771234567',
            '771234567' => '+221771234567',
            '+221 77 123 45 67' => '+221771234567',
            '+221771234567' => '+221771234567',
            '221771234567' => '+221771234567',
            '00221771234567' => '+221771234567',
            '00 221 77 123 45 67' => '+221771234567',
            '(221) 77.123.45.67' => '+221771234567',
            '  78 123 45 67  ' => '+221781234567',
            '76 000 00 00' => '+221760000000',
            '33 821 12 34' => '+221338211234',
        ];

        foreach ($cas as $saisie => $attendu) {
            $this->assertSame($attendu, $normalizer->normalize($saisie), "Saisie : {$saisie}");
        }
    }

    public function test_les_numeros_etrangers_sont_acceptes(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('+33612345678', $normalizer->normalize('+33 6 12 34 56 78'));
        $this->assertSame('+33612345678', $normalizer->normalize('0033612345678'));
    }

    public function test_les_numeros_invalides_donnent_null(): void
    {
        $normalizer = new PhoneNormalizer();

        $invalides = [
            null, '', '   ', 'abc', '77 123 45', '12345678', '881234567', '+221 88 123 45 67',
            '221221771234567', '+221771234567890', '7712345678', '0771234567', '77+123 45 67',
            '+', '+0 123 456 78', '+1234567', '77 123 45 6a',
        ];

        foreach ($invalides as $saisie) {
            $this->assertNull($normalizer->normalize($saisie), 'Saisie : '.var_export($saisie, true));
        }
    }

    public function test_l_affichage_et_le_format_whatsapp(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('+221 77 123 45 67', $normalizer->format('+221771234567'));
        $this->assertSame('+33612345678', $normalizer->format('+33612345678'));
        $this->assertSame('', $normalizer->format(null));

        $this->assertSame('221771234567', $normalizer->forWhatsApp('+221771234567'));
        $this->assertNull($normalizer->forWhatsApp(null));
    }
}