<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Logger;
use App\Services\Contracts\TelegramServiceInterface;
use Override;
use Throwable;

final class SendTelegramNotificationJob implements JobInterface
{
    public function __construct(private readonly TelegramServiceInterface $telegram)
    {
    }

    /** @param array{icon: string, title: string, message: string} $payload */
    #[Override]
    public function handle(array $payload): void
    {
        try {
            $this->telegram->sendAlert(
                $payload['icon'] ?? '🔔',
                $payload['title'] ?? '',
                $payload['message'] ?? '',
            );
        } catch (Throwable $e) {
            Logger::error('[SendTelegramNotificationJob] Error sending Telegram notification', [
                'exception' => $e->getMessage(),
            ]);
            // Do not rethrow — a failed Telegram notification is non-critical
        }
    }
}
