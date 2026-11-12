<?php

namespace App\Controller;

use App\Catalog\QuestCatalog;
use App\Repository\DailyChallengeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(QuestCatalog $catalog, DailyChallengeRepository $daily): Response
    {
        return $this->render('home/index.html.twig', [
            'paths' => $catalog->paths(),
            'latest' => $catalog->latest(6),
            'daily' => $daily->findForDate(new \DateTimeImmutable('today')),
        ]);
    }
}
