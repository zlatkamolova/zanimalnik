<?php

namespace App\Seed;

use App\Entity\Joke;
use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\Setting;
use App\Entity\SignRole;
use App\Entity\Tag;
use App\Entity\Wall;
use App\Entity\WallComment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Fills an EMPTY database with the starting content (paths, roles, About text, tags,
 * the 8 example quests and their PDFs). Never touches a database that already has paths,
 * so later deploys can't overwrite what the admin changed.
 */
final class ContentSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%app.quest_pdf_dir%')] private readonly string $pdfDir,
        #[Autowire('%kernel.project_dir%/resources/seed')] private readonly string $seedFilesDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function isEmpty(): bool
    {
        return 0 === $this->em->getRepository(Path::class)->count([]);
    }

    /**
     * @param bool $withDraft dev only: also add an unpublished example (to see drafts stay hidden) and a few jokes of the day
     *
     * @return array<string, int> what was created, e.g. ['пътеки' => 4, …]; empty when the database wasn't empty
     */
    public function seed(bool $withDraft = false): array
    {
        if (!$this->isEmpty()) {
            return [];
        }

        $paths = [];
        $order = 0;
        foreach (ExampleContent::PATHS as $slug => $data) {
            $this->em->persist($paths[$slug] = (new Path())
                ->setName($data['name'])
                ->setSlug($slug)
                ->setSymbol($data['symbol'])
                ->setColor($data['color'])
                ->setDescription($data['description'])
                ->setSortOrder($order += 10));
        }

        foreach (ExampleContent::SIGN_ROLES as $i => $name) {
            $this->em->persist((new SignRole())->setName($name)->setSortOrder(($i + 1) * 10));
        }

        $this->em->persist(new Setting(Setting::ABOUT_TITLE, ExampleContent::ABOUT_TITLE));
        $this->em->persist(new Setting(Setting::ABOUT_BODY, trim(ExampleContent::ABOUT_BODY)));
        // on a real server the migration has already inserted the privacy texts
        $settings = $this->em->getRepository(Setting::class);
        $settings->getOrCreate(Setting::PRIVACY_TITLE)->setValue(ExampleContent::PRIVACY_TITLE);
        $settings->getOrCreate(Setting::PRIVACY_BODY)->setValue(trim(ExampleContent::PRIVACY_BODY));

        $this->seedWall();

        $tags = [];
        foreach (ExampleContent::TAGS as $slug => $name) {
            $this->em->persist($tags[$slug] = (new Tag())->setName($name)->setSlug($slug));
        }

        $now = new \DateTimeImmutable('today 09:00');
        foreach (ExampleContent::QUESTS as $data) {
            $quest = (new Quest())
                ->setPath($paths[$data['path']])
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
                $quest->addAdditionalPath($paths[$extra]);
            }
            foreach ($data['tags'] as $tag) {
                $quest->addTag($tags[$tag]);
            }
            if (isset($data['pdf'])) {
                $this->filesystem->copy($this->seedFilesDir.'/'.$data['pdf'], $this->pdfDir.'/'.$data['pdf'], true);
                $quest->setPdfPath($data['pdf']);
            }
            $this->em->persist($quest);
        }

        if ($withDraft) {
            $this->em->persist((new Quest())
                ->setPath($paths['slovo'])
                ->setTitle('Шепотът на стълбището')
                ->setSlug('shepotat-na-stalbishteto')
                ->setSummary('Чернова: какво разказват звуците на една обикновена сграда.')
                ->setBody('<p>Текстът предстои.</p>')
                ->setDifficulty(2)
                ->setDuration('една вечер')
                ->setIsPublished(false)
                ->setIsDailyEligible(true));

            foreach (ExampleContent::JOKES as $data) {
                $this->em->persist((new Joke())->setType($data['type'])->setText($data['text']));
            }
        }

        $this->em->flush();

        return [
            'пътеки' => \count($paths),
            'роли' => \count(ExampleContent::SIGN_ROLES),
            'тагове' => \count($tags),
            'куеста' => \count(ExampleContent::QUESTS) + ($withDraft ? 1 : 0),
        ];
    }

    /** The wall of public comments with its first two comments; on a real server the migration has already created it. */
    private function seedWall(): void
    {
        if ($this->em->getRepository(Wall::class)->findOneBy(['slug' => ExampleContent::WALL_SLUG])) {
            return;
        }

        $wall = new Wall(ExampleContent::WALL_SLUG, ExampleContent::WALL_TITLE, new \DateTimeImmutable(ExampleContent::WALL_PUBLISHED_AT));
        $this->em->persist($wall);

        foreach (ExampleContent::WALL_COMMENTS as $data) {
            $this->em->persist(new WallComment(
                $wall,
                null,
                ExampleContent::WALL_COMMENT_NAME,
                $data['message'],
                ExampleContent::WALL_COMMENT_OS,
                WallComment::VIA_FORM,
                hash('sha256', 'seed'),
                hash('sha256', $data['message']),
                null,
                new \DateTimeImmutable($data['created_at']),
            ));
        }
    }
}
