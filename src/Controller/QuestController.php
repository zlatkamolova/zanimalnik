<?php

namespace App\Controller;

use App\Catalog\QuestCatalog;
use App\Entity\JournalEntry;
use App\Entity\User;
use App\Form\JournalEntryType;
use App\Repository\JournalEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class QuestController extends AbstractController
{
    /** All quests with live filters (see App\Twig\Components\QuestBrowser). */
    #[Route('/kuesti', name: 'app_quests', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('quest/index.html.twig');
    }

    #[Route('/kuest/{slug}', name: 'app_quest', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, QuestCatalog $catalog, JournalEntryRepository $entries, #[CurrentUser] ?User $user = null): Response
    {
        $quest = $catalog->findBySlug($slug) ?? throw $this->createNotFoundException('Няма такъв куест.');

        // logged-in visitors can write the quest into their journal right here
        $journal = null;
        if ($user) {
            $journal = [
                'form' => $this->createForm(JournalEntryType::class, new JournalEntry($user, $quest), [
                    'action' => $this->generateUrl('app_journal_add', ['slug' => $quest->getSlug()]),
                ])->createView(),
                'count' => $entries->countFor($user, $quest),
                'last' => $entries->findLastFor($user, $quest),
            ];
        }

        return $this->render('quest/show.html.twig', ['quest' => $quest, 'journal' => $journal]);
    }
}
