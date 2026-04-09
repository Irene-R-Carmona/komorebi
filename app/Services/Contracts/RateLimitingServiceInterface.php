<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Contrato para servicios de rate limiting.
 *
 * Permite testear middlewares que dependen del rate limiter
 * sin necesidad de una base de datos real.
 */
interface RateLimitingServiceInterface
{
    /**
     * Verifica si un identificador está bloqueado para una acción dada.
     *
     * @return array{blocked: bool, minutes_remaining?: int}
     */
    public function isBlocked(string $action, string $identifier): array;

    /**
     * Registra un intento y bloquea si se supera el límite configurado.
     */
    public function recordAttempt(string $action, string $identifier, ?string $ipAddress = null): bool;
}
