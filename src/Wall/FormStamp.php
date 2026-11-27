<?php

namespace App\Wall;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** The signed moment at which the comment form was loaded; a form sent back within seconds was not filled in by a person. */
final class FormStamp
{
    public const MIN_SECONDS = 3;

    public function __construct(#[Autowire('%kernel.secret%')] private readonly string $secret)
    {
    }

    public function create(int $time): string
    {
        return $time.'.'.$this->sign($time);
    }

    /** Seconds since the form was loaded; null when the stamp is missing or was not issued by this site. */
    public function age(string $stamp, int $now): ?int
    {
        [$time, $signature] = explode('.', $stamp, 2) + [1 => ''];
        if (!ctype_digit($time) || !hash_equals($this->sign((int) $time), $signature)) {
            return null;
        }

        return $now - (int) $time;
    }

    private function sign(int $time): string
    {
        return hash_hmac('sha256', 'wall-form|'.$time, $this->secret);
    }
}
