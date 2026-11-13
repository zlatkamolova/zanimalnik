<?php

namespace App\Sign;

use App\Util\Contrast;

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

    /** White or near-black ink, whichever reads better on the colour (WCAG). */
    public function textColor(): string
    {
        return Contrast::textOn($this->hex, '#ffffff', '#1b1612');
    }
}
