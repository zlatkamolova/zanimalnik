<?php

namespace App\Controller;

use App\Catalog\QuestCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QuestController extends AbstractController
{
    /** All quests with live filters (see App\Twig\Components\QuestBrowser). */
    #[Route('/kuesti', name: 'app_quests', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('quest/index.html.twig');
    }

    #[Route('/kuest/{slug}', name: 'app_quest', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, QuestCatalog $catalog): Response
    {
        $quest = $catalog->findBySlug($slug) ?? throw $this->createNotFoundException('Няма такъв куест.');

        return $this->render('quest/show.html.twig', ['quest' => $quest]);
    }
}
