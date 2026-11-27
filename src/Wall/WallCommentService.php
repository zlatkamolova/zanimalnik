<?php

namespace App\Wall;

use App\Entity\Wall;
use App\Entity\WallComment;
use App\Repository\WallCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The only way a comment gets saved, for the form and for the API alike:
 * validation, the ban on links, duplicates, limits, then the record.
 *
 * There is no approval before a comment is shown, so the protection is here.
 * The limits are counted in the comments table itself.
 */
final class WallCommentService
{
    public const PER_MINUTE = 3;
    public const PER_DAY = 20;
    public const WALL_PER_DAY = 200;

    public const CLOSED = 'Коментарите са затворени.';
    public const TOO_FAST = 'Публикувате коментари твърде бързо.';
    public const DUPLICATE = 'Открит е дублиран коментар.';
    public const INVALID = 'Невалидни данни.';
    public const REQUIRED = 'Полето е задължително.';
    public const NO_LINKS = 'Линкове не са позволени.';

    /** field => [shortest, longest, text when outside that range] */
    private const FIELDS = [
        'name' => [2, 40, 'Името трябва да е между 2 и 40 знака.'],
        'message' => [1, 1000, 'Коментарът трябва да е най-много 1000 знака.'],
        'os' => [1, 60, 'Операционната система трябва да е най-много 60 знака.'],
    ];

    private const LINK = '~https?://|www\.|(?<![a-z0-9-])[a-z0-9-]+\.(?:com|net|org|info|biz|bg|ru|io|eu|co|me|ly|xyz|top|site|online|shop|store|app|dev|ai|de|uk|cn|tk|cc|tv|club|link|click)(?![a-z0-9-])|\.бг(?![\p{L}\p{N}])~iu';

    public function __construct(
        private readonly WallCommentRepository $comments,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
    }

    /**
     * @param array<array-key, mixed> $input name, message, os and optionally reply_to_id; anything else is ignored
     *
     * @throws WallCommentException when the comment is not saved
     */
    public function post(Wall $wall, array $input, string $via, ?string $ip, ?string $userAgent, bool $isAdmin = false): WallComment
    {
        if (!$wall->isOpen()) {
            throw new WallCommentException(403, self::CLOSED);
        }

        [$values, $errors] = $this->validate($input, $isAdmin);
        $parent = $this->parent($wall, $input['reply_to_id'] ?? null, $errors);
        if ($errors) {
            throw new WallCommentException(422, self::INVALID, $errors);
        }

        $ipHash = $this->ipHash($ip);
        $messageHash = hash('sha256', mb_strtolower((string) preg_replace('/\s+/u', ' ', $values['message'])));
        if ($this->comments->isDuplicate($wall, $ipHash, $messageHash)) {
            throw new WallCommentException(409, self::DUPLICATE);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $this->checkLimits($wall, $ipHash, $now);

        $comment = new WallComment(
            $wall,
            $parent,
            $values['name'],
            $values['message'],
            $values['os'],
            $via,
            $ipHash,
            $messageHash,
            null === $userAgent || '' === trim($userAgent) ? null : mb_substr(trim($userAgent), 0, 255),
            $now,
        );
        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    /** The same sender always gets the same hash. IPv6 senders are recognised by their network (/64). */
    public function ipHash(?string $ip): string
    {
        $ip = (string) $ip;
        if (str_contains($ip, ':') && false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            $ip = IpUtils::anonymize($ip, 0, 8);
        }

        return hash_hmac('sha256', $ip, $this->secret);
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @return array{array<string, string>, array<string, string>} [clean values, text per wrong field]
     */
    private function validate(array $input, bool $isAdmin): array
    {
        $values = [];
        $errors = [];

        foreach (self::FIELDS as $field => [$min, $max, $lengthMessage]) {
            $value = $input[$field] ?? null;
            if (!\is_string($value) || '' === ($value = trim($value))) {
                $errors[$field] = self::REQUIRED;
                continue;
            }
            $value = str_replace(["\r\n", "\r"], "\n", $value);

            // line breaks are fine in the comment itself, nowhere else
            $controls = 'message' === $field ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
            if (!mb_check_encoding($value, 'UTF-8') || preg_match($controls, $value)) {
                $errors[$field] = 'Полето съдържа непозволени знаци.';
            } elseif (mb_strlen($value) < $min || mb_strlen($value) > $max) {
                $errors[$field] = $lengthMessage;
            } elseif (preg_match(self::LINK, $value)) {
                $errors[$field] = self::NO_LINKS;
            } elseif ('name' === $field && !$isAdmin && WallComment::isReservedName($value)) {
                $errors[$field] = 'Името е заето.';
            }

            $values[$field] = $value;
        }

        return [$values, $errors];
    }

    /**
     * The comment being answered. A reply to a reply joins the same thread.
     *
     * @param array<string, string> $errors
     */
    private function parent(Wall $wall, mixed $replyTo, array &$errors): ?WallComment
    {
        if (null === $replyTo || '' === $replyTo || 0 === $replyTo || '0' === $replyTo) {
            return null;
        }

        $parent = \is_int($replyTo) || (\is_string($replyTo) && ctype_digit($replyTo) && \strlen($replyTo) < 10)
            ? $this->comments->findVisible($wall, (int) $replyTo)
            : null;
        if (!$parent) {
            $errors['reply_to_id'] = 'Коментарът не съществува.';

            return null;
        }

        return $parent->getParent() ?? $parent;
    }

    private function checkLimits(Wall $wall, string $ipHash, \DateTimeImmutable $now): void
    {
        $day = $now->modify('-1 day');
        $minute = $now->modify('-1 minute');

        $mine = $this->comments->timesBySender($ipHash, $day);
        $lastMinute = array_values(array_filter($mine, static fn (\DateTimeImmutable $at) => $at > $minute));
        if (\count($lastMinute) >= self::PER_MINUTE) {
            throw $this->tooFast($lastMinute[\count($lastMinute) - self::PER_MINUTE], '+1 minute', $now);
        }
        if (\count($mine) >= self::PER_DAY) {
            throw $this->tooFast($mine[\count($mine) - self::PER_DAY], '+1 day', $now);
        }

        $all = $this->comments->timesOnWall($wall, $day);
        if (\count($all) >= self::WALL_PER_DAY) {
            throw $this->tooFast($all[\count($all) - self::WALL_PER_DAY], '+1 day', $now);
        }
    }

    /** $blocking is the comment that has to leave the window before the next one is accepted. */
    private function tooFast(\DateTimeImmutable $blocking, string $window, \DateTimeImmutable $now): WallCommentException
    {
        $retryAfter = max(1, $blocking->modify($window)->getTimestamp() - $now->getTimestamp() + 1);

        return new WallCommentException(429, self::TOO_FAST, [], $retryAfter);
    }
}
