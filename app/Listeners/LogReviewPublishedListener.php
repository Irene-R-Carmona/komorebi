<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Logger;
use App\Events\ReviewPublishedEvent;

final class LogReviewPublishedListener
{
    public function __invoke(ReviewPublishedEvent $event): void
    {
        Logger::info('[Review] Nueva review publicada', [
            'review_id' => $event->reviewId,
            'user_id' => $event->userId,
            'rating' => $event->rating,
        ]);
    }
}
