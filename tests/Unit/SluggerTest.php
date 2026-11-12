<?php

namespace App\Tests\Unit;

use App\Service\Slugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SluggerTest extends TestCase
{
    public static function titles(): iterable
    {
        yield ['Кривите пътеки', 'krivite-pateki'];
        yield ['Многоликата дума', 'mnogolikata-duma'];
        yield ['Тълкувателят', 'talkuvatelyat'];
        yield ['Щастие, юнашко ядене!', 'shtastie-yunashko-yadene'];
        yield ['Ден на лявата ръка', 'den-na-lyavata-raka'];
        yield ['  Колко минути до…  ', 'kolko-minuti-do'];
        yield ['История на Мария', 'istoria-na-maria'];
        yield ['Café № 5', 'cafe-no-5'];
        yield ['???', 'n-a'];
    }

    #[DataProvider('titles')]
    public function testSlug(string $title, string $expected): void
    {
        self::assertSame($expected, (new Slugger())->slug($title));
    }

    public function testMaxLength(): void
    {
        $slug = (new Slugger())->slug(str_repeat('дума ', 50), 20);

        self::assertLessThanOrEqual(20, \strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
    }
}
