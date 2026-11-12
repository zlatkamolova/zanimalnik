<?php

namespace App\Tests\Unit;

use App\Sign\DailySignService;
use App\Sign\SignBasis;
use App\Sign\SignInput;
use App\Sign\SignPalette;
use App\Sign\Zodiac;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DailySignServiceTest extends TestCase
{
    private const SOFIA = 'Europe/Sofia';

    private function service(string $now): DailySignService
    {
        return new DailySignService(new MockClock(new \DateTimeImmutable($now, new \DateTimeZone(self::SOFIA))));
    }

    /** @return iterable<string, array{SignInput}> */
    public static function inputs(): iterable
    {
        yield 'зодия' => [new SignInput(SignBasis::Zodiac, 'lav')];
        yield 'име' => [new SignInput(SignBasis::Name, 'Мария')];
        yield 'роля' => [new SignInput(SignBasis::Role, 'skitnikat')];
    }

    #[DataProvider('inputs')]
    public function testSameInputSameDayGivesSameSign(SignInput $input): void
    {
        $morning = $this->service('2026-11-12 00:00:01')->forToday($input);
        $evening = $this->service('2026-11-12 23:59:59')->forToday($input);

        self::assertSame([$morning->colorName, $morning->number], [$evening->colorName, $evening->number]);
    }

    #[DataProvider('inputs')]
    public function testNextDayGivesDifferentSign(SignInput $input): void
    {
        $today = $this->service('2026-11-12 12:00')->forToday($input);
        $tomorrow = $this->service('2026-11-13 12:00')->forToday($input);

        self::assertNotSame([$today->colorName, $today->number], [$tomorrow->colorName, $tomorrow->number]);
    }

    public function testSignChangesOverAWeek(): void
    {
        $input = new SignInput(SignBasis::Zodiac, 'ribi');
        $results = [];
        for ($day = 1; $day <= 7; ++$day) {
            $sign = $this->service(\sprintf('2026-11-%02d 10:00', $day))->forToday($input);
            $results[] = $sign->colorName.'#'.$sign->number;
        }

        self::assertCount(7, array_unique($results), 'Every day of the week should give a new sign.');
    }

    public function testDayFollowsSofiaTimeNotUtc(): void
    {
        $input = new SignInput(SignBasis::Name, 'Иван');
        // 23:30 UTC on the 12th is already 01:30 on the 13th in Sofia
        $utcLate = new DailySignService(new MockClock(new \DateTimeImmutable('2026-11-12 23:30', new \DateTimeZone('UTC'))));
        $sofiaMorning = $this->service('2026-11-13 08:00');

        self::assertSame('2026-11-13', $utcLate->today()->format('Y-m-d'));
        self::assertSame($sofiaMorning->forToday($input)->number, $utcLate->forToday($input)->number);
    }

    public function testNameIsNormalised(): void
    {
        $service = $this->service('2026-11-12 12:00');
        $a = $service->forToday(new SignInput(SignBasis::Name, '  Мария   Петрова '));
        $b = $service->forToday(new SignInput(SignBasis::Name, 'мария петрова'));

        self::assertSame([$a->colorName, $a->number], [$b->colorName, $b->number]);
    }

    public function testBasisIsPartOfTheHash(): void
    {
        $service = $this->service('2026-11-12 12:00');
        // "rak" as a zodiac sign vs. "rak" typed as a name
        $zodiac = $service->forToday(new SignInput(SignBasis::Zodiac, 'rak'));
        $name = $service->forToday(new SignInput(SignBasis::Name, 'rak'));

        self::assertNotSame([$zodiac->colorName, $zodiac->number], [$name->colorName, $name->number]);
    }

    public function testResultIsAlwaysInRangeAndFromThePalette(): void
    {
        $service = $this->service('2026-11-12 12:00');
        $hexes = array_column(SignPalette::COLORS, 'hex', 'name');
        $seenColors = [];

        foreach (array_keys(Zodiac::SIGNS) as $slug) {
            for ($d = 0; $d < 30; ++$d) {
                $sign = $service->forDate(new SignInput(SignBasis::Zodiac, $slug), new \DateTimeImmutable("2026-01-01 +$d days"));
                self::assertGreaterThanOrEqual(1, $sign->number);
                self::assertLessThanOrEqual(99, $sign->number);
                self::assertSame($hexes[$sign->colorName], $sign->hex);
                $seenColors[$sign->colorName] = true;
            }
        }

        self::assertGreaterThan(20, \count($seenColors), 'The palette should be well used.');
    }

    public function testPaletteHas24UniqueNamedColours(): void
    {
        self::assertCount(24, SignPalette::COLORS);
        self::assertCount(24, array_unique(array_column(SignPalette::COLORS, 'name')));
        self::assertCount(24, array_unique(array_column(SignPalette::COLORS, 'hex')));
        foreach (SignPalette::COLORS as $color) {
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color['hex']);
        }
    }

    public function testTextColourHasEnoughContrast(): void
    {
        $service = $this->service('2026-11-12 12:00');
        foreach (SignPalette::COLORS as $color) {
            $sign = new \App\Sign\DailySign(new SignInput(SignBasis::Zodiac, 'lav'), $service->today(), $color['name'], $color['hex'], 7);
            self::assertGreaterThanOrEqual(4.5, self::contrast($color['hex'], $sign->textColor()), $color['name']);
        }
    }

    public function testCookieRoundTrip(): void
    {
        $input = new SignInput(SignBasis::Name, 'Ива-Мария');
        $restored = SignInput::fromCookie($input->toCookie());

        self::assertNotNull($restored);
        self::assertSame(SignBasis::Name, $restored->basis);
        self::assertSame('ива-мария', $restored->value);
    }

    public function testBrokenCookiesAreIgnored(): void
    {
        foreach ([null, '', 'nonsense', 'planet:mars', 'name:', 'name:'.str_repeat('а', 41), "name:\xff\xfe"] as $cookie) {
            self::assertNull(SignInput::fromCookie($cookie), var_export($cookie, true));
        }
    }

    public function testValidationMessagesAreBulgarian(): void
    {
        self::assertSame(['Напишете име.'], SignInput::validate(SignBasis::Name, '   '));
        self::assertSame(['Името може да е най-много 40 знака.'], SignInput::validate(SignBasis::Name, str_repeat('я', 41)));
        self::assertSame(['Изберете зодия.'], SignInput::validate(SignBasis::Zodiac, 'pluto'));
        self::assertSame(['Изберете роля.'], SignInput::validate(SignBasis::Role, 'nyama', ['pazitelyat']));
        self::assertSame([], SignInput::validate(SignBasis::Role, 'pazitelyat', ['pazitelyat']));
        self::assertSame([], SignInput::validate(SignBasis::Name, 'Ани'));
    }

    private static function contrast(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            [$r, $g, $b] = array_map(static function (string $c): float {
                $v = hexdec($c) / 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        };
        [$hi, $lo] = [max($lum($a), $lum($b)), min($lum($a), $lum($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }
}
