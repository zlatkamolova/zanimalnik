<?php

namespace App\DataFixtures;

use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * 8 example quests (2 per path) + 1 draft. Texts are placeholders to be edited in the admin.
 */
final class QuestFixtures extends Fixture implements DependentFixtureInterface
{
    private const TAGS = [
        'navan' => 'навън',
        'vkashti' => 'вкъщи',
        'sam' => 'сам',
        's-priyateli' => 'с приятели',
        's-detsa' => 'с деца',
        'bez-telefon' => 'без телефон',
        's-tefter' => 'с тефтер',
    ];

    private const QUESTS = [
        [
            'slug' => 'sedem-zhalti-neshta',
            'path' => 'vzor',
            'title' => 'Седем жълти неща',
            'summary' => 'По обичайния си път открий седем жълти неща, които досега не си забелязвал.',
            'difficulty' => 1,
            'duration' => 'един ден',
            'materials' => null,
            'tags' => ['navan', 'sam', 's-detsa'],
            'days_ago' => 20,
            'body' => <<<'HTML'
                <p>Жълтото е навсякъде, но рядко го виждаме. Днес то е твоята плячка.</p>
                <h3>Правила</h3>
                <ul>
                  <li>Мини по път, който познаваш добре: до работа, до училище, до магазина.</li>
                  <li>Намери <strong>седем</strong> жълти неща, които досега не си забелязвал.</li>
                  <li>Не се броят коли, такси и светофари — те са твърде лесни.</li>
                  <li>Ако искаш, снимай всяко откритие.</li>
                </ul>
                <h3>Въпрос за накрая</h3>
                <p>Кое от седемте е било там най-отдавна?</p>
                HTML,
        ],
        [
            'slug' => 'kartata-po-pamet',
            'path' => 'vzor',
            'title' => 'Картата по памет',
            'summary' => 'Нарисувай по памет карта на квартала си, после я провери на място.',
            'difficulty' => 2,
            'duration' => 'един следобед',
            'materials' => 'лист, молив, цветен химикал',
            'tags' => ['navan', 'sam', 's-tefter'],
            'days_ago' => 18,
            'pdf' => 'kartata-po-pamet.pdf',
            'body' => <<<'HTML'
                <p>Колко добре познаваш мястото, където живееш? Проверката е проста и малко смиряваща.</p>
                <h3>Правила</h3>
                <ol>
                  <li>Без да поглеждаш навън или в телефона, нарисувай карта на улиците около дома си: поне пет пресечки.</li>
                  <li>Отбележи всичко, което помниш: магазини, дървета, пейки, табели, кофи.</li>
                  <li>Вземи картата и мини по същия маршрут. С цветен химикал поправи грешките и добави пропуснатото.</li>
                </ol>
                <h3>Въпрос за накрая</h3>
                <p>Какво се оказа най-голямото „бяло петно“ на твоята карта?</p>
                HTML,
        ],
        [
            'slug' => 'mnogolikata-duma',
            'path' => 'slovo',
            'title' => 'Многоликата дума',
            'summary' => 'Избери дума с много значения и цял ден събирай лицата ѝ — в разговори, надписи и книги.',
            'difficulty' => 2,
            'duration' => 'един ден',
            'materials' => 'тефтер или бележки в телефона',
            'tags' => ['sam', 's-priyateli', 's-tefter'],
            'days_ago' => 16,
            'pdf' => 'mnogolikata-duma.pdf',
            'body' => <<<'HTML'
                <p>Някои думи имат по няколко лица. <em>Ключ</em> отваря врата, но е и ключ на чучур, и музикален знак, и отговор на загадка. Днес си ловец на лица.</p>
                <h3>Правила</h3>
                <ol>
                  <li>Сутринта избери една многолика дума. Идеи: <em>ключ, коса, лист, рак, бряг, връзка, глава, корен, мишка, кран</em>.</li>
                  <li>През деня записвай всяко място, където я срещнеш: в разговор, на табела, в книга, в песен.</li>
                  <li>До всяко срещане отбележи кое лице на думата е било.</li>
                  <li>Вечерта преброй лицата. Измисли едно ново значение, което думата още няма.</li>
                </ol>
                <h3>Въпрос за накрая</h3>
                <p>Кое лице на думата те изненада най-много?</p>
                HTML,
        ],
        [
            'slug' => 'nekazanoto',
            'path' => 'slovo',
            'title' => 'Неказаното',
            'summary' => 'Цял ден слушай не какво казват хората, а какво остава между думите им.',
            'difficulty' => 3,
            'duration' => 'един ден',
            'materials' => 'тефтер',
            'tags' => ['s-priyateli', 's-tefter'],
            'days_ago' => 14,
            'body' => <<<'HTML'
                <p>В почти всеки разговор има нещо, което никой не изрича. Пауза, смяна на темата, усмивка не на място.</p>
                <h3>Правила</h3>
                <ul>
                  <li>През деня бъди внимателен слушател. Не задавай въпроси за „неказаното“ — само наблюдавай.</li>
                  <li>Запиши <strong>три</strong> момента, в които си усетил, че нещо остава неизречено.</li>
                  <li>За всеки момент запиши какво мислиш, че е било то — с една дума.</li>
                </ul>
                <p>Пази бележките си за себе си. Тук никой не бива разобличаван.</p>
                <h3>Въпрос за накрая</h3>
                <p>Имаше ли нещо, което ти самият не каза днес?</p>
                HTML,
        ],
        [
            'slug' => 'kolko-minuti-do',
            'path' => 'orakul',
            'title' => 'Колко минути до…',
            'summary' => 'Преди всяка задача за деня предскажи колко ще продължи. Вечерта сравни.',
            'difficulty' => 1,
            'duration' => 'един ден',
            'materials' => 'тефтер, часовник',
            'tags' => ['sam', 'vkashti', 's-tefter'],
            'days_ago' => 12,
            'body' => <<<'HTML'
                <p>Всички мислим, че знаем колко време отнемат нещата. Оракулът проверява.</p>
                <h3>Правила</h3>
                <ol>
                  <li>Сутринта избери пет обичайни задачи: душ, път до работа, пазаруване, готвене, домашно.</li>
                  <li>Преди всяка запиши прогнозата си в минути.</li>
                  <li>Засечи истинското време.</li>
                  <li>Вечерта сравни: къде позна, къде сгреши и в коя посока.</li>
                </ol>
                <h3>Въпрос за накрая</h3>
                <p>По-оптимист ли си или по-песимист, когато става дума за време?</p>
                HTML,
        ],
        [
            'slug' => 'tri-prognozi-za-denya',
            'path' => 'orakul',
            'title' => 'Три прогнози за деня',
            'summary' => 'Сутринта направи три смели прогнози за деня, запечатай ги и ги отвори вечерта.',
            'difficulty' => 2,
            'duration' => 'един ден',
            'materials' => 'лист хартия и плик',
            'tags' => ['sam', 's-priyateli', 'vkashti'],
            'days_ago' => 10,
            'body' => <<<'HTML'
                <p>Една прогноза е лесна за гадаене. Три — вече са характер.</p>
                <h3>Правила</h3>
                <ol>
                  <li>Сутринта запиши три прогнози за деня. Например: кой ще ти се обади пръв, каква ще е първата дума, която ще чуеш от непознат, какво ще е времето в 17:00.</li>
                  <li>Сгъни листа и го запечатай в плик. Не го отваряй до вечерта.</li>
                  <li>Вечерта отвори плика и оцени всяка прогноза: позната, почти, мимо.</li>
                </ol>
                <h3>Въпрос за накрая</h3>
                <p>Коя от прогнозите ти беше предчувствие и коя — сметка?</p>
                HTML,
        ],
        [
            'slug' => 'nov-pat-kam-vkashti',
            'path' => 'krivite-pateki',
            'extra_paths' => ['vzor'],
            'title' => 'Нов път към вкъщи',
            'summary' => 'Прибери се по маршрут, по който никога не си минавал — без карта в телефона.',
            'difficulty' => 2,
            'duration' => 'един час',
            'materials' => null,
            'tags' => ['navan', 'bez-telefon', 'sam'],
            'days_ago' => 8,
            'body' => <<<'HTML'
                <p>Навикът ни води по един и същ път. Днес го излъгай.</p>
                <h3>Правила</h3>
                <ul>
                  <li>Тръгни към вкъщи по улица, по която никога не си вървял.</li>
                  <li>Не отваряй карта в телефона. Ориентирай се по слънцето, по сградите, по питане.</li>
                  <li>По пътя забележи три неща, които не би видял по стария маршрут.</li>
                </ul>
                <p>Това е куест на кръстопът: тренира и смелостта, и погледа.</p>
                <h3>Въпрос за накрая</h3>
                <p>Колко пъти поиска да се върнеш на стария път?</p>
                HTML,
        ],
        [
            'slug' => 'den-na-lyavata-raka',
            'path' => 'krivite-pateki',
            'title' => 'Ден на лявата ръка',
            'summary' => 'Цял ден прави дребните неща с другата си ръка и виж какво се променя.',
            'difficulty' => 1,
            'duration' => 'един ден',
            'materials' => null,
            'tags' => ['vkashti', 'sam', 's-detsa'],
            'days_ago' => 6,
            'body' => <<<'HTML'
                <p>Ако си десняк — днес си левак. Ако си левак — обратно.</p>
                <h3>Правила</h3>
                <ul>
                  <li>Мий си зъбите, отключвай вратата, бъркай чая и отваряй вратите с другата ръка.</li>
                  <li>Напиши едно изречение с другата ръка сутринта и същото изречение вечерта. Сравни.</li>
                  <li>Не се насилвай при опасни неща: нож, котлон, колело.</li>
                </ul>
                <h3>Въпрос за накрая</h3>
                <p>Кое беше най-трудно — и кое неочаквано лесно?</p>
                HTML,
        ],
    ];

    public function __construct(
        #[Autowire('%app.quest_pdf_dir%')] private readonly string $pdfDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function getDependencies(): array
    {
        return [PathFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $tags = [];
        foreach (self::TAGS as $slug => $name) {
            $manager->persist($tags[$slug] = (new Tag())->setName($name)->setSlug($slug));
        }

        $now = new \DateTimeImmutable('today 09:00');

        foreach (self::QUESTS as $data) {
            $quest = (new Quest())
                ->setPath($this->getReference('path-'.$data['path'], Path::class))
                ->setTitle($data['title'])
                ->setSlug($data['slug'])
                ->setSummary($data['summary'])
                ->setBody(trim($data['body']))
                ->setDifficulty($data['difficulty'])
                ->setDuration($data['duration'])
                ->setMaterials($data['materials'])
                ->setIsPublished(true)
                ->setIsDailyEligible(true)
                ->setPublishedAt($now->modify(\sprintf('-%d days', $data['days_ago'])));

            foreach ($data['extra_paths'] ?? [] as $extra) {
                $quest->addAdditionalPath($this->getReference('path-'.$extra, Path::class));
            }
            foreach ($data['tags'] as $tag) {
                $quest->addTag($tags[$tag]);
            }
            if (isset($data['pdf'])) {
                $quest->setPdfPath($this->copyPdf($data['pdf']));
            }

            $manager->persist($quest);
        }

        // A draft: must never be visible on the public site
        $manager->persist((new Quest())
            ->setPath($this->getReference('path-slovo', Path::class))
            ->setTitle('Шепотът на стълбището')
            ->setSlug('shepotat-na-stalbishteto')
            ->setSummary('Чернова: какво разказват звуците на една обикновена сграда.')
            ->setBody('<p>Текстът предстои.</p>')
            ->setDifficulty(2)
            ->setDuration('една вечер')
            ->setIsPublished(false)
            ->setIsDailyEligible(true));

        $manager->flush();
    }

    /** Copies a sample PDF into the uploads folder and returns the stored file name. */
    private function copyPdf(string $file): string
    {
        $this->filesystem->copy(__DIR__.'/files/'.$file, $this->pdfDir.'/'.$file, true);

        return $file;
    }
}
