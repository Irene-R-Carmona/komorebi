<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Clase base para Services.
 *
 * Proporciona helpers de validación y logging reutilizables.
 * No contiene PDO ni lógica transaccional — ver TransactionalService.
 */
abstract class BaseService
{
    // ─── Logging ──────────────────────────────────────────────────

    protected function logInfo(string $message, array $context = []): void
    {
        Logger::info('[' . static::class . '] ' . $message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Logger::error('[' . static::class . '] ' . $message, $context);
    }

    protected function logDebug(string $message, array $context = []): void
    {
        Logger::debug('[' . static::class . '] ' . $message, $context);
    }

    protected function logWarning(string $message, array $context = []): void
    {
        Logger::warning('[' . static::class . '] ' . $message, $context);
    }

    protected function logCritical(string $message, array $context = []): void
    {
        Logger::critical('[' . static::class . '] ' . $message, $context);
    }
}
