<?php

namespace App\Util;

/** WCAG 2 colour contrast helpers. */
final class Contrast
{
    public const LIGHT = '#fbf6ea';
    public const DARK = '#1b1612';

    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (3 === \strlen($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return 0.0;
        }

        [$r, $g, $b] = array_map(static function (string $c): float {
            $v = hexdec($c) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** The light or dark text colour that reads better on $background. */
    public static function textOn(string $background, string $light = self::LIGHT, string $dark = self::DARK): string
    {
        return self::ratio($background, $light) >= self::ratio($background, $dark) ? $light : $dark;
    }
}
