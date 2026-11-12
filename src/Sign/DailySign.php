<?php

namespace App\Sign;

/** The result: a named colour and a number (1–99) for one visitor on one day. */
final readonly class DailySign
{
    public function __construct(
        public SignInput $input,
        public \DateTimeImmutable $date,
        public string $colorName,
        public string $hex,
        public int $number,
    ) {
    }

    /** Ink or white, whichever reads better on the colour (WCAG relative luminance). */
    public function textColor(): string
    {
        [$r, $g, $b] = array_map(
            static function (string $c): float {
                $v = hexdec($c) / 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($this->hex, '#'), 2),
        );
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // contrast with white vs. with near-black ink (#1b1612, L ≈ 0.008)
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.058) ? '#ffffff' : '#1b1612';
    }
}
