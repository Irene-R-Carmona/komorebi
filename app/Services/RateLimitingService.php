<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Services\Contracts\RateLimitingServiceInterface;
use PDO;

/**
 * Servicio de Rate Limiting
 */
final class RateLimitingService implements RateLimitingServiceInterface
{
    private PDO $db;

    private const CONFIG = [
        'login' => ['max_attempts' => 5, 'lockout_minutes' => 15],
        'password_reset' => ['max_attempts' => 3, 'lockout_minutes' => 30],
        'email_verification' => ['max_attempts' => 5, 'lockout_minutes' => 10],
        'registration' => ['max_attempts' => 3, 'lockout_minutes' => 60],
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        // In test environment reset rate limits to avoid cross-test pollution
        $appEnv = Env::get('APP_ENV');
        if ($appEnv === 'testing') {
            try {
                $this->db->exec('DELETE FROM rate_limits');
            } catch (\Throwable $e) {
                // Silenciar: si la tabla no existe o la DB no está disponible en tests, no bloquear
            }
        }
    }

    /**
     * Obtener configuración de una acción
     *
     * @param string $action
     *
     * @return array{max_attempts: int, lockout_minutes: int}
     */
    private function getConfig(string $action): array
    {
        return self::CONFIG[$action] ?? ['max_attempts' => 5, 'lockout_minutes' => 15];
    }

    /**
     * Registrar intento de una acción
     *
     * @param string      $action
     * @param string      $identifier (email, IP, user_id, etc)
     * @param string|null $ipAddress
     *
     * @return boolean
     */
    #[\Override]
    public function recordAttempt(string $action, string $identifier, ?string $ipAddress = null): bool
    {
        $config = $this->getConfig($action);

        $stmt = $this->db->prepare(
            'SELECT id, attempt_count FROM rate_limits WHERE action = :action AND identifier = :id'
        );
        $stmt->execute(['action' => $action, 'id' => $identifier]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $stmt = $this->db->prepare(
                'INSERT INTO rate_limits (action, identifier, attempt_count, last_attempt, ip_address)
                 VALUES (:action, :id, 1, NOW(), :ip)'
            );

            return $stmt->execute(['action' => $action, 'id' => $identifier, 'ip' => $ipAddress]);
        }

        $attemptCount = (int) $existing['attempt_count'] + 1;
        $lockUntil = null;

        if ($attemptCount >= $config['max_attempts']) {
            $lockUntil = \date('Y-m-d H:i:s', \time() + ($config['lockout_minutes'] * 60));
        }

        $stmt = $this->db->prepare(
            'UPDATE rate_limits SET attempt_count = :count, last_attempt = NOW(),
             locked_until = :lock, ip_address = :ip WHERE id = :id'
        );

        return $stmt->execute([
            'count' => $attemptCount,
            'lock' => $lockUntil,
            'ip' => $ipAddress,
            'id' => (int) $existing['id'],
        ]);
    }

    /**
     * Verificar si un identificador está bloqueado
     *
     * @param string $action
     * @param string $identifier
     *
     * @return array{blocked: bool, minutes_remaining?: int}
     */
    #[\Override]
    public function isBlocked(string $action, string $identifier): array
    {
        $stmt = $this->db->prepare(
            'SELECT locked_until FROM rate_limits
             WHERE action = :action AND identifier = :id AND locked_until > NOW()'
        );
        $stmt->execute(['action' => $action, 'id' => $identifier]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['blocked' => false];
        }

        $lockedUntil = \strtotime($record['locked_until']);
        $minutesRemaining = (int) \ceil(($lockedUntil - \time()) / 60);

        return ['blocked' => true, 'minutes_remaining' => \max(1, $minutesRemaining)];
    }

    /**
     * Obtener número de intentos recientes (últimas 24h)
     *
     * @param string $action
     * @param string $identifier
     *
     * @return integer
     */
    public function getRecentAttempts(string $action, string $identifier): int
    {
        $stmt = $this->db->prepare(
            'SELECT attempt_count FROM rate_limits
             WHERE action = :action AND identifier = :id
             AND last_attempt > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute(['action' => $action, 'id' => $identifier]);
        $record = $stmt->fetch();

        return $record ? (int) $record['attempt_count'] : 0;
    }

    /**
     * Limpiar intentos fallidos (después de login exitoso, etc)
     *
     * @param string $action
     * @param string $identifier
     *
     * @return boolean
     */
    public function clearAttempts(string $action, string $identifier): bool
    {
        $stmt = $this->db->prepare('DELETE FROM rate_limits WHERE action = :action AND identifier = :id');

        return $stmt->execute(['action' => $action, 'id' => $identifier]);
    }

    /**
     * Limpiar registros antiguos (mantenimiento)
     *
     * @return integer Registros eliminados
     */
    public function cleanupOldRecords(): int
    {
        $stmt = $this->db->query('DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 30 DAY)');

        return $stmt->rowCount();
    }
}
