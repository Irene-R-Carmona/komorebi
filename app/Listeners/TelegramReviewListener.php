<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Queue;
use App\Core\WideEvent;
use App\Events\ReviewPublishedEvent;
use App\Jobs\SendTelegramNotificationJob;

final class TelegramReviewListener
{
    public function __invoke(ReviewPublishedEvent $event): void
    {
        $stars = \str_repeat('⭐', $event->rating);
        $message = "Reseña #: {$event->reviewId}\n"
            . "Puntuación: {$stars} ({$event->rating}/5)\n"
            . "Comentario: {$event->comment}";

        Queue::push(SendTelegramNotificationJob::class, [
            'icon' => '⭐',
            'title' => 'Nueva reseña publicada',
            'message' => $message,
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ]);
    }
}
