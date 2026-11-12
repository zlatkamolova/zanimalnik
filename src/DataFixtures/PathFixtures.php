<?php

namespace App\DataFixtures;

use App\Entity\Path;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class PathFixtures extends Fixture
{
    public const PATHS = [
        'vzor' => [
            'name' => 'Взор',
            'symbol' => 'eye',
            'color' => '#2f6f73',
            'description' => 'Пътеката на погледа. Тук се учим да забелязваме: дребното, скритото, онова, покрай което минаваме всеки ден. Внимание, ориентация и концентрация — често с фотоапарат в ръка.',
        ],
        'slovo' => [
            'name' => 'Слово',
            'symbol' => 'quill',
            'color' => '#8a3b12',
            'description' => 'Пътеката на думите. Значения, които се крият една в друга, думи с много лица и онова, което остава неизказано. За хора, които обичат да слушат и да подбират.',
        ],
        'orakul' => [
            'name' => 'Оракул',
            'symbol' => 'moon',
            'color' => '#4b3a7a',
            'description' => 'Пътеката на предчувствието. Първо прогноза, после действие, накрая сравнение: колко добре познаваш света и себе си? Тук интуицията се тренира като мускул.',
        ],
        'krivite-pateki' => [
            'name' => 'Кривите пътеки',
            'symbol' => 'spiral',
            'color' => '#5b6b2a',
            'description' => 'Пътеката на заобикалянето. Нови маршрути, странни ограничения и обичайни неща, направени по необичаен начин. Нестандартното мислене започва от една крачка встрани.',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $order = 0;
        foreach (self::PATHS as $slug => $data) {
            $path = (new Path())
                ->setName($data['name'])
                ->setSlug($slug)
                ->setSymbol($data['symbol'])
                ->setColor($data['color'])
                ->setDescription($data['description'])
                ->setSortOrder($order += 10);

            $manager->persist($path);
            $this->addReference('path-'.$slug, $path);
        }

        $manager->flush();
    }
}
