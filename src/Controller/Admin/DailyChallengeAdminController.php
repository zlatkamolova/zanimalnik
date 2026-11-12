<?php

namespace App\Controller\Admin;

use App\Entity\DailyChallenge;
use App\Entity\Path;
use App\Entity\Quest;
use App\Repository\DailyChallengeRepository;
use App\Repository\PathRepository;
use App\Repository\QuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Overview of the next 7 days × all paths; the admin can pin a quest for a day by hand
 * or hand the day back to the automatic pick.
 */
#[IsGranted('ROLE_ADMIN')]
#[AdminRoute('/daily-challenges', name: 'daily_challenges')]
final class DailyChallengeAdminController extends AbstractController
{
    public const DAYS = 7;

    public function __construct(
        private readonly PathRepository $paths,
        private readonly QuestRepository $quests,
        private readonly DailyChallengeRepository $challenges,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[AdminRoute('/', name: 'index', options: ['methods' => ['GET']])]
    public function index(): Response
    {
        $paths = $this->paths->findAllOrdered();

        $days = [];
        foreach ($this->dateRange() as $date) {
            $byPath = [];
            foreach ($this->challenges->findForDate($date) as $challenge) {
                $byPath[$challenge->getPath()->getId()] = $challenge;
            }
            $days[] = ['date' => $date, 'challenges' => $byPath];
        }

        $options = [];
        foreach ($paths as $path) {
            $options[$path->getId()] = $this->quests->findPublishedByPath($path);
        }

        return $this->render('admin/daily_challenges/index.html.twig', [
            'paths' => $paths,
            'days' => $days,
            'options' => $options,
        ]);
    }

    /** Pins a quest for a date + path (marks it as manual so the automatic pick keeps it). */
    #[AdminRoute('/set', name: 'set', options: ['methods' => ['POST']])]
    public function set(Request $request): RedirectResponse
    {
        [$date, $path] = $this->readDateAndPath($request);
        $quest = $this->em->find(Quest::class, $request->request->getInt('quest'));

        if (!$date || !$path || !$quest || !$quest->isVisible() || !\in_array($path, $quest->getAllPaths(), true)) {
            $this->addFlash('danger', 'Изборът не е валиден. Опитайте отново.');

            return $this->backToIndex();
        }

        $challenge = $this->challenges->findOneForDateAndPath($date, $path)
            ?? (new DailyChallenge())->setDate($date)->setPath($path);
        $challenge->setQuest($quest)->setIsManual(true);
        $this->em->persist($challenge);
        $this->em->flush();

        $this->addFlash('success', \sprintf('%s · %s: избран е „%s“.', $date->format('d.m'), $path->getName(), $quest->getTitle()));

        return $this->backToIndex();
    }

    /** Removes the entry; the automatic pick fills the day again. */
    #[AdminRoute('/reset', name: 'reset', options: ['methods' => ['POST']])]
    public function reset(Request $request): RedirectResponse
    {
        [$date, $path] = $this->readDateAndPath($request);

        if ($date && $path && ($challenge = $this->challenges->findOneForDateAndPath($date, $path))) {
            $this->em->remove($challenge);
            $this->em->flush();
            $this->addFlash('success', \sprintf('%s · %s: денят е върнат на автоматичен избор.', $date->format('d.m'), $path->getName()));
        }

        return $this->backToIndex();
    }

    /** @return list<\DateTimeImmutable> */
    private function dateRange(): array
    {
        $today = new \DateTimeImmutable('today');

        return array_map(fn (int $i) => $today->modify("+$i days"), range(0, self::DAYS - 1));
    }

    /** @return array{0: ?\DateTimeImmutable, 1: ?Path} */
    private function readDateAndPath(Request $request): array
    {
        if (!$this->isCsrfTokenValid('daily-challenge', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Формулярът е изтекъл. Презаредете страницата.');

            return [null, null];
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('date')) ?: null;
        $allowed = array_map(fn (\DateTimeImmutable $d) => $d->format('Y-m-d'), $this->dateRange());
        if (!$date || !\in_array($date->format('Y-m-d'), $allowed, true)) {
            $date = null;
        }

        return [$date, $this->em->find(Path::class, $request->request->getInt('path'))];
    }

    private function backToIndex(): RedirectResponse
    {
        return $this->redirectToRoute('admin_daily_challenges_index');
    }
}
