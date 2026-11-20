<?php

namespace App\Controller;

use App\Catalog\QuestCatalog;
use App\Entity\JournalEntry;
use App\Entity\User;
use App\Form\JournalEntryType;
use App\Repository\JournalEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** The visitor's private journal. Everything under /dnevnik needs a login (see security.yaml). */
final class JournalController extends AbstractController
{
    private const PER_PAGE = 20;

    #[Route('/dnevnik', name: 'app_journal', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, JournalEntryRepository $entries): Response
    {
        $total = $entries->countFor($user);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('stranitsa', 1)), $pages);

        return $this->render('journal/index.html.twig', [
            'entries' => $entries->findPage($user, $page, self::PER_PAGE),
            'total' => $total,
            'quest_count' => $entries->countDistinctQuests($user),
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    /** The form itself is on the quest page. */
    #[Route('/dnevnik/zapis/{slug}', name: 'app_journal_add', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function add(string $slug, Request $request, #[CurrentUser] User $user, QuestCatalog $catalog, EntityManagerInterface $em): Response
    {
        $quest = $catalog->findBySlug($slug) ?? throw $this->createNotFoundException('Няма такъв куест.');

        $entry = new JournalEntry($user, $quest);
        $form = $this->createForm(JournalEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entry);
            $em->flush();
            $this->addFlash('success', \sprintf('„%s“ е записан в дневника.', $quest->getTitle()));

            return $this->redirectToRoute('app_journal');
        }

        return $this->render('journal/edit.html.twig', ['entry' => $entry, 'form' => $form, 'delete_form' => null]);
    }

    #[Route('/dnevnik/{id}/redaktsiya', name: 'app_journal_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, #[CurrentUser] User $user, JournalEntryRepository $entries, EntityManagerInterface $em): Response
    {
        $entry = $this->own($entries, $id, $user);
        $form = $this->createForm(JournalEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Записът е променен.');

            return $this->redirectToRoute('app_journal');
        }

        return $this->render('journal/edit.html.twig', [
            'entry' => $entry,
            'form' => $form,
            'delete_form' => $this->deleteForm($id),
        ]);
    }

    #[Route('/dnevnik/{id}/iztrivane', name: 'app_journal_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, #[CurrentUser] User $user, JournalEntryRepository $entries, EntityManagerInterface $em): Response
    {
        $entry = $this->own($entries, $id, $user);
        $form = $this->deleteForm($id);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($entry);
            $em->flush();
            $this->addFlash('success', 'Записът е изтрит.');
        }

        return $this->redirectToRoute('app_journal');
    }

    /** Somebody else's entry does not exist as far as this visitor is concerned. */
    private function own(JournalEntryRepository $entries, int $id, User $user): JournalEntry
    {
        return $entries->findOneBy(['id' => $id, 'user' => $user]) ?? throw $this->createNotFoundException('Няма такъв запис.');
    }

    private function deleteForm(int $id): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder(null, ['csrf_token_id' => 'submit'])
            ->setAction($this->generateUrl('app_journal_delete', ['id' => $id]))
            ->getForm();
    }
}
