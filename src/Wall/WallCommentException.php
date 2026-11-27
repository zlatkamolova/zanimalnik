<?php

namespace App\Wall;

/** A comment that was not saved: the HTTP status, the text for the visitor and, for 422, the text per field. */
final class WallCommentException extends \RuntimeException
{
    /** @param array<string, string> $fields */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $fields = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
