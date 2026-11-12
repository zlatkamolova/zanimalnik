<?php

namespace App\Sign;

/**
 * The visitor's choice. The value is normalised, so "  Мария " and "мария" give the same sign.
 * Serialised into the cookie as "<basis>:<urlencoded value>".
 */
final readonly class SignInput
{
    public const NAME_MAX_LENGTH = 40;

    public string $value;

    public function __construct(
        public SignBasis $basis,
        string $value,
    ) {
        $this->value = self::normalize($basis, $value);
    }

    public static function normalize(SignBasis $basis, string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        return SignBasis::Name === $basis ? mb_strtolower($value) : $value;
    }

    /** Validation messages in Bulgarian; empty array = valid. Roles are checked against $roleSlugs. */
    public static function validate(SignBasis $basis, string $value, array $roleSlugs = []): array
    {
        $value = trim($value);

        return match ($basis) {
            SignBasis::Zodiac => Zodiac::exists($value) ? [] : ['Изберете зодия.'],
            SignBasis::Role => \in_array($value, $roleSlugs, true) ? [] : ['Изберете роля.'],
            SignBasis::Name => match (true) {
                '' === $value => ['Напишете име.'],
                mb_strlen($value) > self::NAME_MAX_LENGTH => [\sprintf('Името може да е най-много %d знака.', self::NAME_MAX_LENGTH)],
                !preg_match('/\p{L}/u', $value) => ['Името трябва да съдържа поне една буква.'],
                default => [],
            },
        };
    }

    public function toCookie(): string
    {
        return $this->basis->value.':'.rawurlencode($this->value);
    }

    /** Returns null for anything malformed (old or hand-edited cookies are simply ignored). */
    public static function fromCookie(?string $cookie): ?self
    {
        if (!$cookie || !str_contains($cookie, ':') || \strlen($cookie) > 400) {
            return null;
        }

        [$basis, $value] = explode(':', $cookie, 2);
        $basis = SignBasis::tryFrom($basis);
        $value = rawurldecode($value);

        if (!$basis || !mb_check_encoding($value, 'UTF-8') || '' === trim($value) || mb_strlen($value) > self::NAME_MAX_LENGTH) {
            return null;
        }

        return new self($basis, $value);
    }
}
