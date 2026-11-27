<?php

namespace App\Controller\Api;

use App\Entity\Wall;
use App\Entity\WallComment;
use App\Repository\WallCommentRepository;
use App\Repository\WallRepository;
use App\Wall\WallCommentException;
use App\Wall\WallCommentService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON API of the wall of public comments. No session, no cookies, no CORS headers.
 * Comments are saved by WallCommentService, the same one the form uses.
 */
#[AsController]
final class BotSpeakController
{
    public const MAX_BODY = 4096;
    private const LIST_SIZE = 100;

    public function __construct(
        private readonly WallRepository $walls,
        private readonly WallCommentRepository $comments,
        #[Autowire('%app.default_wall%')] private readonly string $defaultWall,
    ) {
    }

    #[Route('/api/bot-speak', name: 'app_api_bot_speak', methods: ['POST'])]
    public function post(Request $request, WallCommentService $service): JsonResponse
    {
        if ('application/json' !== strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'))[0]))) {
            return $this->json(['error' => 'Очаква се application/json.'], 415);
        }

        $body = $request->getContent();
        if (\strlen($body) > self::MAX_BODY) {
            return $this->json(['error' => 'Заявката е твърде голяма.'], 413);
        }

        $data = json_decode($body, true);
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            return $this->json(['error' => 'Невалиден JSON.'], 400);
        }

        $wall = $this->wall($data['wall'] ?? null);
        if (!$wall) {
            return $this->json(['error' => WallCommentService::INVALID, 'fields' => ['wall' => 'Стената не съществува.']], 422);
        }

        try {
            $comment = $service->post($wall, $data, WallComment::VIA_API, $request->getClientIp(), $request->headers->get('User-Agent'));
        } catch (WallCommentException $e) {
            $response = $this->json(['error' => $e->getMessage()] + ($e->fields ? ['fields' => $e->fields] : []), $e->status);
            if (null !== $e->retryAfter) {
                $response->headers->set('Retry-After', (string) $e->retryAfter);
            }

            return $response;
        }

        return $this->json(['id' => $comment->getId()], 201);
    }

    #[Route('/api/bot-speak', name: 'app_api_bot_speak_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $wall = $this->wall($request->query->get('wall'));
        if (!$wall) {
            return $this->json(['error' => 'Стената не съществува.'], 404);
        }

        return $this->json(array_map(static fn (WallComment $comment) => [
            'id' => $comment->getId(),
            'name' => $comment->getName(),
            'message' => $comment->getMessage(),
            'os' => $comment->getOs(),
            'reply_to_id' => $comment->getParent()?->getId(),
            'created_at' => $comment->getCreatedAt()->format(\DATE_ATOM),
        ], array_reverse($this->comments->findLatestVisible($wall, self::LIST_SIZE))));
    }

    private function wall(mixed $slug): ?Wall
    {
        $slug ??= $this->defaultWall;

        return \is_string($slug) ? $this->walls->findOneBySlug($slug) : null;
    }

    /** @param array<array-key, mixed> $data */
    private function json(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse(status: $status);
        $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->setData($data);

        return $response;
    }
}
