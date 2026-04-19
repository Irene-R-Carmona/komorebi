<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\SessionRepositoryInterface;
use PDO;

final class SessionRepository implements SessionRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function createOrUpdate(
        int $userId,
        string $sessionId,
        string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        string $now,
        string $expiresAt
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO active_sessions
             (user_id, session_id, ip_address, user_agent, device_name, last_activity, created_at, expires_at)
             VALUES (:user_id, :session_id, :ip, :ua, :device, :last, :created, :expires)
             ON DUPLICATE KEY UPDATE
                 last_activity = VALUES(last_activity),
                 ip_address    = VALUES(ip_address),
                 user_agent    = VALUES(user_agent),
                 device_name   = VALUES(device_name),
                 expires_at    = VALUES(expires_at)'
        );

        return $stmt->execute([
            'user_id'    => $userId,
            'session_id' => $sessionId,
            'ip'         => $ipAddress,
            'ua'         => $userAgent,
            'device'     => $deviceName,
            'last'       => $now,
            'created'    => $now,
            'expires'    => $expiresAt,
        ]);
    }

    public function findActiveByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, session_id, ip_address, user_agent, device_name,
                    last_activity, created_at, expires_at
             FROM active_sessions
             WHERE user_id = :user_id AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY last_activity DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, session_id, ip_address, user_agent, device_name,
                    last_activity, created_at, expires_at, revoked_at
             FROM active_sessions WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function updateActivity(string $sessionId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE active_sessions SET last_activity = NOW()
             WHERE session_id = :session_id AND revoked_at IS NULL'
        );

        return $stmt->execute(['session_id' => $sessionId]);
    }

    public function revoke(int $id, int $revokedBy, string $reason): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE active_sessions
             SET revoked_at = NOW(), revoke_reason = :reason, revoked_by = :by
             WHERE id = :id'
        );

        return $stmt->execute(['reason' => $reason, 'by' => $revokedBy, 'id' => $id]);
    }

    public function revokeAllExcept(int $userId, string $currentSessionId, int $revokedBy): int
    {
        $stmt = $this->db->prepare(
            "UPDATE active_sessions
             SET revoked_at = NOW(), revoke_reason = 'security_logout_all', revoked_by = :by
             WHERE user_id = :user_id AND session_id != :current AND revoked_at IS NULL"
        );
        $stmt->execute(['by' => $revokedBy, 'user_id' => $userId, 'current' => $currentSessionId]);

        return $stmt->rowCount();
    }

    public function deleteExpired(): int
    {
        return $this->db->query('DELETE FROM active_sessions WHERE expires_at < NOW()')->rowCount();
    }
}
