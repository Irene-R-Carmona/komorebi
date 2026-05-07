<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface SessionRepositoryInterface
{
    public function createOrUpdate(
        int $userId,
        string $sessionId,
        string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        string $now,
        string $expiresAt
    ): bool;

    /** @return array<int, array<string, mixed>> */
    public function findActiveByUserId(int $userId): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    public function updateActivity(string $sessionId): bool;

    public function revoke(int $id, int $revokedBy, string $reason): bool;

    /** Revoca todas excepto la sesión actual. Devuelve número de filas afectadas. */
    public function revokeAllExcept(int $userId, string $currentSessionId, int $revokedBy): int;

    /** Elimina sesiones expiradas. Devuelve número de filas eliminadas. */
    public function deleteExpired(): int;
}
