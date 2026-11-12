<?php

namespace App\Sign;

use Psr\Clock\ClockInterface;

/**
 * Deterministic sign of the day: the same input on the same (Sofia) day always gives
 * the same colour and number; the next day they change.
 *
 *   hash = sha256("<Y-m-d>|<basis>|<normalised value>")
 *   colour = first 4 bytes  mod 24   → SignPalette
 *   number = next 4 bytes   mod 99 + 1
 *
 * Nothing is stored: the name only travels in the visitor's own cookie.
 */
final class DailySignService
{
    public const TIMEZONE = 'Europe/Sofia';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function forToday(SignInput $input): DailySign
    {
        return $this->forDate($input, $this->today());
    }

    public function forDate(SignInput $input, \DateTimeImmutable $date): DailySign
    {
        $day = $date->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d');
        $hash = hash('sha256', $day.'|'.$input->basis->value.'|'.$input->value, true);

        $colorIndex = unpack('N', substr($hash, 0, 4))[1] % \count(SignPalette::COLORS);
        $number = unpack('N', substr($hash, 4, 4))[1] % 99 + 1;
        $color = SignPalette::COLORS[$colorIndex];

        return new DailySign($input, new \DateTimeImmutable($day, new \DateTimeZone(self::TIMEZONE)), $color['name'], $color['hex'], $number);
    }

    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone(self::TIMEZONE))->setTime(0, 0);
    }
}
