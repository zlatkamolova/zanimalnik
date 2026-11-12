<?php

namespace App\Sign;

/** What the visitor bases their sign of the day on. */
enum SignBasis: string
{
    case Zodiac = 'zodiac';
    case Name = 'name';
    case Role = 'role';

    public function label(): string
    {
        return match ($this) {
            self::Zodiac => 'Зодия',
            self::Name => 'Име',
            self::Role => 'Роля',
        };
    }
}
