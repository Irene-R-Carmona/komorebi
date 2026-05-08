<?php

declare(strict_types=1);

namespace App\Workers;

use Override;

/**
 * EmailWorker — Procesa jobs de la cola 'emails'.
 *
 * Worker especializado para envío de emails asíncrono.
 * Consume la cola 'emails' y ejecuta jobs de tipo SendEmailJob.
 */
final class EmailWorker extends AbstractWorker
{
    public const string QUEUE_NAME = 'emails';
    private const int MAX_RETRIES = 3;
    private const int RETRY_DELAY = 60; // segundos

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
        return '/tmp/worker-email-heartbeat';
    }

    #[Override]
    protected function getWorkerName(): string
    {
        return 'EmailWorker';
    }
}
