<?php

namespace App\Twig;

use App\Util\Contrast;
use Twig\Attribute\AsTwigFilter;

final class ColorExtension
{
    /** {{ path.color|text_on }} → the light or dark colour that reads best on it. */
    #[AsTwigFilter('text_on')]
    public function textOn(string $background): string
    {
        return Contrast::textOn($background);
    }
}
