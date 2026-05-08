<?php

declare(strict_types=1);

namespace App\Events;

use DateTimeImmutable;

final readonly class ReviewPublishedEvent
{
    public function __construct(
        public int $reviewId,
        public int $userId,
        public int $rating,
        public string $comment,
        public DateTimeImmutable $publishedAt
    ) {
    }
}
