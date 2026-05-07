<?php

declare(strict_types=1);

namespace App\Workers;

use Override;

/**
 * NotificationWorker — Procesa jobs de la cola 'notifications'.
 *
 * Worker especializado para notificaciones (push, SMS, webhooks).
 * Consume la cola 'notifications' y ejecuta jobs de notificación.
 */
final class NotificationWorker extends AbstractWorker
{
    private const string QUEUE_NAME = 'notifications';
    private const int MAX_RETRIES = 5; // Más reintentos para notificaciones
    private const int RETRY_DELAY = 30; // segundos

    #[Override]
    protected function getQueueName(): string
    {
        return self::QUEUE_NAME;
    }

    #[Override]
    protected function getMaxRetries(): int
    {
        return self::MAX_RETRIES;
    }

    #[Override]
    protected function getRetryDelay(): int
    {
        return self::RETRY_DELAY;
    }

    #[Override]
    protected function getHeartbeatFilePath(): string
    {
        return '/tmp/worker-notification-heartbeat';
    }

    #[Override]
    protected function getWorkerName(): string
    {
        return 'NotificationWorker';
    }
}
