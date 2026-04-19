<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\AuthLogRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio para logs de autenticación.
 *
 * Encapsula las queries de análisis sobre auth_audit_logs.
 */
final class AuthLogRepository extends AbstractRepository implements AuthLogRepositoryInterface
{
    #[Override]
    protected function getTable(): string
    {
        return 'auth_audit_logs';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'user_id', 'event_type', 'ip_address', 'success', 'created_at'];
    }

    /**
     * Buscar IPs con actividad sospechosa (múltiples fallos de login recientes).
     *
     * @return array<int, array{ip_address: string, failed_attempts: int, last_attempt: string}>
     */
    public function findSuspiciousActivity(int $minutesBack = 15, int $threshold = 5): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT
                ip_address,
                COUNT(*) as failed_attempts,
                MAX(created_at) as last_attempt
             FROM auth_audit_logs
             WHERE event_type = 'failed_login'
               AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
             GROUP BY ip_address
             HAVING failed_attempts >= :threshold
             ORDER BY failed_attempts DESC"
        );
        $stmt->execute(['minutes' => $minutesBack, 'threshold' => $threshold]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logEvent(
        ?int $userId,
        string $eventType,
        string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        bool $success,
        ?string $reason
    ): bool {
        $stmt = $this->getDb()->prepare(
            'INSERT INTO auth_audit_logs
             (user_id, event_type, ip_address, user_agent, device_name, success, reason)
             VALUES (:user_id, :event, :ip, :ua, :device, :success, :reason)'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'event'   => $eventType,
            'ip'      => $ipAddress,
            'ua'      => $userAgent,
            'device'  => $deviceName,
            'success' => $success ? 1 : 0,
            'reason'  => $reason,
        ]);
    }

    public function getHistory(int $userId, int $limit = 20): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT event_type, ip_address, user_agent, device_name, success, reason, created_at
             FROM auth_audit_logs
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findFiltered(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'aal.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'aal.event_type = :event_type';
            $params['event_type'] = $filters['event_type'];
        }
        if (isset($filters['success'])) {
            $where[] = 'aal.success = :success';
            $params['success'] = $filters['success'] ? 1 : 0;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(aal.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(aal.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['ip_address'])) {
            $where[] = 'aal.ip_address LIKE :ip_address';
            $params['ip_address'] = '%' . $filters['ip_address'] . '%';
        }

        $whereClause = \implode(' AND ', $where);

        $countStmt = $this->getDb()->prepare("SELECT COUNT(*) FROM auth_audit_logs aal WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->getDb()->prepare(
            "SELECT aal.id, aal.user_id, aal.event_type, aal.ip_address, aal.user_agent,
                    aal.device_name, aal.success, aal.reason, aal.created_at,
                    u.name AS user_name, u.email AS user_email
             FROM auth_audit_logs aal
             LEFT JOIN users u ON aal.user_id = u.id
             WHERE {$whereClause}
             ORDER BY aal.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function getStats(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = \implode(' AND ', $where);

        $totalsStmt = $this->getDb()->prepare(
            "SELECT COUNT(*) as total_events,
                    SUM(IF(event_type = 'login' AND success = 1, 1, 0)) as successful_logins,
                    SUM(IF(event_type = 'failed_login' OR (event_type = 'login' AND success = 0), 1, 0)) as failed_logins,
                    SUM(IF(event_type = 'lockout', 1, 0)) as lockouts,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
             FROM auth_audit_logs WHERE $whereClause"
        );
        $totalsStmt->execute($params);

        $byTypeStmt = $this->getDb()->prepare(
            "SELECT event_type, COUNT(*) as count
             FROM auth_audit_logs WHERE {$whereClause}
             GROUP BY event_type ORDER BY count DESC"
        );
        $byTypeStmt->execute($params);

        $topIpsStmt = $this->getDb()->prepare(
            "SELECT ip_address, COUNT(*) as count
             FROM auth_audit_logs WHERE $whereClause AND ip_address IS NOT NULL
             GROUP BY ip_address ORDER BY count DESC LIMIT 10"
        );
        $topIpsStmt->execute($params);

        return [
            'totals'        => $totalsStmt->fetch(PDO::FETCH_ASSOC),
            'events_by_type' => $byTypeStmt->fetchAll(PDO::FETCH_ASSOC),
            'top_ips'       => $topIpsStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
