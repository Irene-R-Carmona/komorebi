<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\AuthLogRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use Override;

final class SessionManagementService implements SessionManagementServiceInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly AuthLogRepositoryInterface $authLogRepo
    ) {
    }

    #[Override]
    public function createSession(
        int $userId,
        string $sessionId,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $deviceName = null,
        int $expiresInSeconds = 86400
    ): bool {
        $now = \date('Y-m-d H:i:s');
        $expiresAt = \date('Y-m-d H:i:s', \time() + $expiresInSeconds);

        return $this->sessionRepo->createOrUpdate(
            $userId,
            $sessionId,
            $ipAddress,
            $userAgent,
            $deviceName,
            $now,
            $expiresAt
        );
    }

    #[Override]
    public function getActiveSessions(int $userId): array
    {
        return $this->sessionRepo->findActiveByUserId($userId);
    }

    public function getSessionById(int $sessionRecordId): ?array
    {
        return $this->sessionRepo->findById($sessionRecordId);
    }

    public function updateSessionActivity(string $sessionId): bool
    {
        return $this->sessionRepo->updateActivity($sessionId);
    }

    public function revokeSession(int $sessionRecordId, int $revokedBy, string $reason = 'user_requested'): bool
    {
        return $this->sessionRepo->revoke($sessionRecordId, $revokedBy, $reason);
    }

    #[Override]
    public function revokeSessionForUser(int $userId, int $sessionRecordId, string $reason = 'user_requested'): bool
    {
        $session = $this->sessionRepo->findById($sessionRecordId);

        if (!$session || (int) $session['user_id'] !== $userId) {
            return false;
        }

        return $this->sessionRepo->revoke($sessionRecordId, $userId, $reason);
    }

    #[Override]
    public function revokeAllOtherSessions(int $userId, string $currentSessionId, int $revokedBy): int
    {
        return $this->sessionRepo->revokeAllExcept($userId, $currentSessionId, $revokedBy);
    }

    public function cleanupExpiredSessions(): int
    {
        return $this->sessionRepo->deleteExpired();
    }

    #[Override]
    public function logAuthEvent(
        ?int $userId,
        string $eventType,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $deviceName = null,
        bool $success = true,
        ?string $reason = null
    ): bool {
        return $this->authLogRepo->logEvent(
            $userId,
            $eventType,
            $ipAddress,
            $userAgent,
            $deviceName,
            $success,
            $reason
        );
    }

    #[Override]
    public function getAuthHistory(int $userId, int $limit = 20): array
    {
        return $this->authLogRepo->getHistory($userId, $limit);
    }
}
