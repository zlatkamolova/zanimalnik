<?php

namespace App\Controller;

use App\Catalog\QuestCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PathController extends AbstractController
{
    #[Route('/pateki/{slug}', name: 'app_path', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, QuestCatalog $catalog): Response
    {
        $path = $catalog->pathBySlug($slug) ?? throw $this->createNotFoundException('Няма такава пътека.');

        return $this->render('path/show.html.twig', [
            'path' => $path,
            'quests' => $catalog->forPath($path),
            'paths' => $catalog->paths(),
        ]);
    }
}
