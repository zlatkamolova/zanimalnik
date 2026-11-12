<?php

namespace App\DataFixtures;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class SettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->persist(new Setting(Setting::ABOUT_TITLE, 'За Занималника'));
        $manager->persist(new Setting(Setting::ABOUT_BODY, <<<'HTML'
            <p>Занималникът е място за малки приключения в обикновените дни. Тук няма екрани, точки и класации — има куестове: игриви задачи, които се изпълняват навън, вкъщи, по пътя до работа или училище.</p>
            <p>Куестовете са подредени в четири пътеки. <strong>Взор</strong> тренира погледа, <strong>Слово</strong> — усета за думите, <strong>Оракул</strong> — интуицията, а <strong>Кривите пътеки</strong> — смелостта да опиташ по друг начин.</p>
            <p>Всеки ден по една задача от всяка пътека става предизвикателство на деня. А знакът на деня ти подсказва цвят и число — просто така, за настроение.</p>
            HTML));

        $manager->flush();
    }
}
