<?php

namespace App\Sign;

/** The 12 zodiac signs: slug => [name, symbol]. */
final class Zodiac
{
    public const SIGNS = [
        'oven' => ['Овен', '♈'],
        'telets' => ['Телец', '♉'],
        'bliznatsi' => ['Близнаци', '♊'],
        'rak' => ['Рак', '♋'],
        'lav' => ['Лъв', '♌'],
        'deva' => ['Дева', '♍'],
        'vezni' => ['Везни', '♎'],
        'skorpion' => ['Скорпион', '♏'],
        'strelets' => ['Стрелец', '♐'],
        'kozirog' => ['Козирог', '♑'],
        'vodoley' => ['Водолей', '♒'],
        'ribi' => ['Риби', '♓'],
    ];

    public static function exists(string $slug): bool
    {
        return isset(self::SIGNS[$slug]);
    }

    public static function name(string $slug): string
    {
        return self::SIGNS[$slug][0] ?? $slug;
    }
}
