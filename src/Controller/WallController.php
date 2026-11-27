<?php

namespace App\Controller;

use App\Entity\Wall;
use App\Entity\WallComment;
use App\Repository\WallCommentRepository;
use App\Repository\WallRepository;
use App\Wall\FormStamp;
use App\Wall\WallCommentException;
use App\Wall\WallCommentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The wall of public comments: one page that looks like a blog post, with its own template
 * (templates/wall/) and nothing of the site's layout. Comments are saved by WallCommentService,
 * the same one POST /api/bot-speak uses.
 */
final class WallController extends AbstractController
{
    private const DATE = ['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}', 'slug' => '[a-z0-9-]+'];
    private const INVALID_REQUEST = 'Невалидна заявка.';

    /** field of the service => label in the form */
    private const LABELS = ['message' => 'Коментар', 'name' => 'Име', 'os' => 'Операционна система', 'reply_to_id' => 'Отговор'];

    public function __construct(
        private readonly WallRepository $walls,
        private readonly WallCommentRepository $comments,
        private readonly FormStamp $stamp,
        private readonly ClockInterface $clock,
    ) {
    }

    /** The only link to the wall (in the footer of the site) points here. */
    #[Route('/blog', name: 'app_blog', methods: ['GET'])]
    public function blog(#[Autowire('%app.default_wall%')] string $defaultWall): Response
    {
        $wall = $this->walls->findOneBySlug($defaultWall) ?? throw $this->createNotFoundException();

        return $this->redirectToRoute('app_wall', $wall->getRouteParams());
    }

    #[Route('/{year}/{month}/{day}/{slug}/', name: 'app_wall', requirements: self::DATE, methods: ['GET'])]
    public function show(string $year, string $month, string $day, string $slug, Request $request): Response
    {
        $wall = $this->wall($year, $month, $day, $slug);

        $pages = $this->comments->threadPages($wall);
        $pageCount = max(1, \count($pages));
        $page = $request->query->getInt('cpage', $pageCount);
        if ($page < 1 || $page > $pageCount) {
            throw $this->createNotFoundException();
        }

        $threads = $pages[$page - 1] ?? [];
        $ids = [];
        foreach ($threads as $thread) {
            array_push($ids, $thread['comment'], ...$thread['replies']);
        }

        $replyTo = $request->query->getInt('replytocom');

        return $this->render('wall/show.html.twig', [
            'wall' => $wall,
            'threads' => $threads,
            'comments' => $this->comments->findByIds($ids),
            'comment_count' => $this->comments->countVisible($wall),
            'page' => $page,
            'page_count' => $pageCount,
            'reply_to' => $replyTo > 0 ? $this->comments->findVisible($wall, $replyTo) : null,
            'latest' => $this->comments->findLatestVisible($wall, 5),
            'stamp' => $this->stamp->create($this->clock->now()->getTimestamp()),
        ]);
    }

    #[Route('/{year}/{month}/{day}/{slug}/', name: 'app_wall_comment', requirements: self::DATE, methods: ['POST'])]
    public function comment(string $year, string $month, string $day, string $slug, Request $request, WallCommentService $service): Response
    {
        $wall = $this->wall($year, $month, $day, $slug);
        $form = $request->request;
        $replyTo = $form->getString('comment_parent');
        $back = $this->generateUrl('app_wall', $wall->getRouteParams() + (ctype_digit($replyTo) && $replyTo > 0 ? ['replytocom' => $replyTo] : [])).'#respond';

        if (!$this->isCsrfTokenValid('submit', $form->getString('_token'))) {
            return $this->error(403, self::INVALID_REQUEST, $back);
        }

        // a field people never see; whoever fills it in gets what looks like a success
        if ('' !== trim($form->getString('url'))) {
            return $this->redirect($this->generateUrl('app_wall', $wall->getRouteParams()).'#comments', Response::HTTP_SEE_OTHER);
        }

        $age = $this->stamp->age($form->getString('ts'), $this->clock->now()->getTimestamp());
        if (null === $age) {
            return $this->error(422, self::INVALID_REQUEST, $back);
        }
        if ($age < FormStamp::MIN_SECONDS) {
            return $this->error(429, WallCommentService::TOO_FAST, $back, retryAfter: FormStamp::MIN_SECONDS - $age);
        }

        try {
            $comment = $service->post(
                $wall,
                ['name' => $form->getString('author'), 'message' => $form->getString('comment'), 'os' => $form->getString('os'), 'reply_to_id' => $replyTo],
                WallComment::VIA_FORM,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                $this->isGranted('ROLE_ADMIN'),
            );
        } catch (WallCommentException $e) {
            return $this->error($e->status, $e->getMessage(), $back, $e->fields, $e->retryAfter);
        }

        return $this->redirect($this->commentUrl($comment), Response::HTTP_SEE_OTHER);
    }

    /** "Скрий" and "Скрий всички от този подател" next to a comment. */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/blog/{slug}/skrivane/{id}', name: 'app_wall_hide', requirements: ['slug' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['POST'])]
    public function hide(string $slug, int $id, Request $request, EntityManagerInterface $em): Response
    {
        $wall = $this->walls->findOneBySlug($slug) ?? throw $this->createNotFoundException();
        $comment = $this->comments->findOneBy(['id' => $id, 'wall' => $wall]) ?? throw $this->createNotFoundException();
        $this->checkToken($request);

        if ($request->request->getBoolean('all')) {
            $this->comments->hideAllBySender($wall, $comment->getIpHash());
        } else {
            $comment->setIsHidden(true);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('app_wall', $wall->getRouteParams()).'#comments', Response::HTTP_SEE_OTHER);
    }

    /** "Затвори коментарите" / "Отвори коментарите". */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/blog/{slug}/zatvaryane', name: 'app_wall_toggle', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function toggle(string $slug, Request $request, EntityManagerInterface $em): Response
    {
        $wall = $this->walls->findOneBySlug($slug) ?? throw $this->createNotFoundException();
        $this->checkToken($request);

        $wall->setIsOpen(!$wall->isOpen());
        $em->flush();

        return $this->redirect($this->generateUrl('app_wall', $wall->getRouteParams()).'#respond', Response::HTTP_SEE_OTHER);
    }

    /** The address has to carry the wall's own publishing date; any other date does not exist. */
    private function wall(string $year, string $month, string $day, string $slug): Wall
    {
        $wall = $this->walls->findOneBySlug($slug);
        if (!$wall || $wall->getRouteParams() !== ['year' => $year, 'month' => $month, 'day' => $day, 'slug' => $slug]) {
            throw $this->createNotFoundException();
        }

        return $wall;
    }

    private function checkToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('submit', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException(self::INVALID_REQUEST);
        }
    }

    /** The page of comments on which the comment is shown, with its anchor. */
    private function commentUrl(WallComment $comment): string
    {
        $wall = $comment->getWall();
        $root = ($comment->getParent() ?? $comment)->getId();
        $pages = $this->comments->threadPages($wall);

        $params = $wall->getRouteParams();
        foreach ($pages as $i => $threads) {
            if ($i < \count($pages) - 1 && \in_array($root, array_column($threads, 'comment'), true)) {
                $params['cpage'] = $i + 1;
            }
        }

        return $this->generateUrl('app_wall', $params).'#comment-'.$comment->getId();
    }

    /**
     * A bare page with the text and a link back.
     *
     * @param array<string, string> $fields
     */
    private function error(int $status, string $message, string $back, array $fields = [], ?int $retryAfter = null): Response
    {
        $lines = [];
        foreach ($fields as $field => $text) {
            $lines[] = (self::LABELS[$field] ?? $field).': '.$text;
        }

        $response = $this->render('wall/error.html.twig', ['message' => $message, 'lines' => $lines, 'back' => $back], new Response(status: $status));
        if (null !== $retryAfter) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }
}
