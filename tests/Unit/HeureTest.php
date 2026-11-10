<?php

namespace Tests\Unit;

use App\Support\Heure;
use PHPUnit\Framework\TestCase;

class HeureTest extends TestCase
{
    public function test_conversion_en_minutes(): void
    {
        $this->assertSame(570, Heure::toMinutes('09:30'));
        $this->assertSame(570, Heure::toMinutes('09:30:00'));
        $this->assertSame(0, Heure::toMinutes('00:00'));
        $this->assertSame(1439, Heure::toMinutes('23:59'));
    }

    public function test_formats(): void
    {
        $this->assertSame('09:30', Heure::format(570));
        $this->assertSame('00:05', Heure::format(5));
        $this->assertSame('09:30:00', Heure::sql(570));
    }
}