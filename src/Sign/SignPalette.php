<?php

namespace App\Sign;

/**
 * Curated palette for the sign of the day: 24 named colours, never a random hex.
 * Order matters (the hash picks by index) — append new colours at the end only if you
 * accept that everyone's colours change.
 */
final class SignPalette
{
    /** @var list<array{name: string, hex: string}> */
    public const COLORS = [
        ['name' => 'Ръждив залез', 'hex' => '#b7472a'],
        ['name' => 'Мед от липа', 'hex' => '#d9a441'],
        ['name' => 'Мъх след дъжд', 'hex' => '#5f7a3a'],
        ['name' => 'Морска сол', 'hex' => '#7fa7b5'],
        ['name' => 'Синя полунощ', 'hex' => '#1f3a68'],
        ['name' => 'Лавандулов шепот', 'hex' => '#9c8ac4'],
        ['name' => 'Узряла слива', 'hex' => '#6b2d5c'],
        ['name' => 'Зрял нар', 'hex' => '#b0283f'],
        ['name' => 'Праскова на перваза', 'hex' => '#f2a07b'],
        ['name' => 'Пясък в джоба', 'hex' => '#d8c49a'],
        ['name' => 'Пепел от огнище', 'hex' => '#6e6a65'],
        ['name' => 'Мастило', 'hex' => '#232a3d'],
        ['name' => 'Борова сянка', 'hex' => '#2e5244'],
        ['name' => 'Лимонова кора', 'hex' => '#e8d44d'],
        ['name' => 'Тухла и бръшлян', 'hex' => '#8d4a3a'],
        ['name' => 'Утринна мъгла', 'hex' => '#c9d3d6'],
        ['name' => 'Канела', 'hex' => '#a0562c'],
        ['name' => 'Зелен чай', 'hex' => '#9db37a'],
        ['name' => 'Кехлибар', 'hex' => '#e08e2b'],
        ['name' => 'Розова глина', 'hex' => '#c98b86'],
        ['name' => 'Тюркоазен извор', 'hex' => '#2a9d8f'],
        ['name' => 'Сажди и звезди', 'hex' => '#3b3b4f'],
        ['name' => 'Маков цвят', 'hex' => '#d2452f'],
        ['name' => 'Вечерен тютюн', 'hex' => '#7a5c3e'],
    ];
}
