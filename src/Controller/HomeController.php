<?php

namespace App\Controller;

use App\Catalog\QuestCatalog;
use App\Daily\DailyJokeService;
use App\Daily\DailyPickService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(QuestCatalog $catalog, DailyPickService $daily, DailyJokeService $jokes): Response
    {
        return $this->render('home/index.html.twig', [
            'paths' => $catalog->paths(),
            'latest' => $catalog->latest(6),
            // creates today's challenges if the nightly cron hasn't run
            'daily' => $daily->ensureForDate(),
            // chosen on the first visit of the day; null when the admin hasn't added any
            'joke' => $jokes->forDate(),
        ]);
    }
}
