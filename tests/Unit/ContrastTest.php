<?php

namespace App\Tests\Unit;

use App\Util\Contrast;
use PHPUnit\Framework\TestCase;

final class ContrastTest extends TestCase
{
    public function testKnownRatios(): void
    {
        self::assertEqualsWithDelta(21.0, Contrast::ratio('#000000', '#ffffff'), 0.01);
        self::assertEqualsWithDelta(1.0, Contrast::ratio('#777777', '#777777'), 0.01);
    }

    public function testTextOnPicksTheReadableColour(): void
    {
        self::assertSame(Contrast::LIGHT, Contrast::textOn('#2f6f73'));
        self::assertSame(Contrast::DARK, Contrast::textOn('#e8d44d'));
        self::assertSame(Contrast::DARK, Contrast::textOn('#fff'));
    }

    public function testAnyPathColourGetsAtLeastGraphicContrast(): void
    {
        // whatever colour the admin picks, the seal symbol stays visible (WCAG 1.4.11: 3:1)
        for ($i = 0; $i < 4096; ++$i) {
            $hex = \sprintf('#%02x%02x%02x', ($i >> 8) * 17, (($i >> 4) & 15) * 17, ($i & 15) * 17);
            self::assertGreaterThanOrEqual(3.0, Contrast::ratio($hex, Contrast::textOn($hex)), $hex);
        }
    }
}
