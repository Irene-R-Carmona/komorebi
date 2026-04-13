<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\AuthLogRepositoryInterface;
use PDO;

/**
 * Repositorio para logs de autenticación.
 *
 * Encapsula las queries de análisis sobre auth_audit_logs.
 */
final class AuthLogRepository extends AbstractRepository implements AuthLogRepositoryInterface
{
    #[\Override]
    protected function getTable(): string
    {
        return 'auth_audit_logs';
    }

    #[\Override]
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
}
