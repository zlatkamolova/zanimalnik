<?php

namespace App\Service;

/**
 * Bulgarian → Latin slugs using the official Bulgarian transliteration
 * (Закон за транслитерацията): щ→sht, ъ→a, ю→yu, я→ya, ж→zh, ц→ts …
 * Deterministic and independent of the ICU version on the server.
 */
final class Slugger
{
    private const MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
        'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sht', 'ъ' => 'a', 'ь' => 'y',
        'ю' => 'yu', 'я' => 'ya',
    ];

    public function slug(string $text, int $maxLength = 120): string
    {
        $text = mb_strtolower(trim($text));
        // "-ия" at the end of a word is written "-ia" (Мария → maria, история → istoria)
        $text = preg_replace('/ия\b/u', 'ia', $text);
        $text = strtr($text, self::MAP);
        // anything else non-ASCII (accents etc.) → closest ASCII
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
        $text = trim($text, '-');

        if (\strlen($text) > $maxLength) {
            $text = rtrim(substr($text, 0, $maxLength), '-');
        }

        return '' !== $text ? $text : 'n-a';
    }
}
