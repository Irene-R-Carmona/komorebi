<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modelo AuthAuditLog
 *
 * Gestiona logs de eventos de autenticación y seguridad.
 */
final class AuthAuditLog
{
    private PDO $db;

    // Tipos de eventos
    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_FAILED_LOGIN = 'failed_login';
    public const EVENT_PASSWORD_RESET = 'password_reset';
    public const EVENT_EMAIL_VERIFIED = 'email_verified';
    public const EVENT_SESSION_REVOKED = 'session_revoked';
    public const EVENT_LOCKOUT = 'lockout';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Registra un evento de autenticación
     *
     * @param string       $eventType Tipo de evento (constantes EVENT_*)
     * @param integer|null $userId    ID del usuario (null si no se conoce aún)
     * @param boolean      $success   Si el evento fue exitoso
     * @param string|null  $reason    Razón del fallo (opcional)
     * @return integer ID del log creado
     */
    public static function log(
        string $eventType,
        ?int $userId = null,
        bool $success = true,
        ?string $reason = null
    ): int {
        $db = Database::getConnection();

        // Obtener información del request
        $ipAddress = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $deviceName = self::parseDeviceName($userAgent);

        $sql = 'INSERT INTO auth_audit_logs (
                    user_id, event_type, ip_address, user_agent,
                    device_name, success, reason
                ) VALUES (
                    :user_id, :event_type, :ip_address, :user_agent,
                    :device_name, :success, :reason
                )';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'event_type' => $eventType,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_name' => $deviceName,
            'success' => $success ? 1 : 0,
            'reason' => $reason,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Obtiene logs de autenticación con filtros
     *
     * @param array   $filters Filtros opcionales
     * @param integer $limit   Límite de resultados
     * @param integer $offset  Offset para paginación
     * @return array ['data' => [...], 'total' => int]
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        // Filtro por usuario
        if (!empty($filters['user_id'])) {
            $where[] = 'aal.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        // Filtro por tipo de evento
        if (!empty($filters['event_type'])) {
            $where[] = 'aal.event_type = :event_type';
            $params['event_type'] = $filters['event_type'];
        }

        // Filtro por éxito/fallo
        if (isset($filters['success'])) {
            $where[] = 'aal.success = :success';
            $params['success'] = $filters['success'] ? 1 : 0;
        }

        // Filtro por fecha desde
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(aal.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        // Filtro por fecha hasta
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(aal.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        // Búsqueda por IP
        if (!empty($filters['ip_address'])) {
            $where[] = 'aal.ip_address LIKE :ip_address';
            $params['ip_address'] = '%' . $filters['ip_address'] . '%';
        }

        $whereClause = \implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM auth_audit_logs aal WHERE $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos
        $sql = "SELECT
                    aal.id,
                    aal.user_id,
                    aal.event_type,
                    aal.ip_address,
                    aal.user_agent,
                    aal.device_name,
                    aal.success,
                    aal.reason,
                    aal.created_at,
                    u.name as user_name,
                    u.email as user_email
                FROM auth_audit_logs aal
                LEFT JOIN users u ON aal.user_id = u.id
                WHERE {$whereClause}
                ORDER BY aal.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Obtiene estadísticas de autenticación
     *
     * @param array $filters Filtros opcionales
     * @return array Estadísticas agrupadas
     */
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

        // Totales
        $sql = "SELECT
                    COUNT(*) as total_events,
                    SUM(IF(event_type = 'login' AND success = 1, 1, 0)) as successful_logins,
                    SUM(IF(event_type = 'failed_login' OR (event_type = 'login' AND success = 0), 1, 0)) as failed_logins,
                    SUM(IF(event_type = 'lockout', 1, 0)) as lockouts,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM auth_audit_logs
                WHERE $whereClause";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $totals = $stmt->fetch();

        // Eventos por tipo
        $sql = "SELECT event_type, COUNT(*) as count
                FROM auth_audit_logs
                WHERE {$whereClause}
                GROUP BY event_type
                ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $eventsByType = $stmt->fetchAll();

        // IPs más activas
        $sql = "SELECT ip_address, COUNT(*) as count
                FROM auth_audit_logs
                WHERE $whereClause AND ip_address IS NOT NULL
                GROUP BY ip_address
                ORDER BY count DESC
                LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $topIps = $stmt->fetchAll();

        return [
            'totals' => $totals,
            'events_by_type' => $eventsByType,
            'top_ips' => $topIps,
        ];
    }

    /**
     * Obtiene intentos de login fallidos recientes para una IP
     *
     * @param string  $ipAddress IP a verificar
     * @param integer $minutes   Ventana de tiempo en minutos
     * @return integer Número de intentos fallidos
     */
    public function getRecentFailedLogins(string $ipAddress, int $minutes = 15): int
    {
        $sql = 'SELECT COUNT(*)
                FROM auth_audit_logs
                WHERE ip_address = :ip_address
                  AND event_type = :event_type
                  AND success = 0
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ip_address' => $ipAddress,
            'event_type' => self::EVENT_FAILED_LOGIN,
            'minutes' => $minutes,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene historial de autenticación de un usuario
     *
     * @param integer $userId ID del usuario
     * @param integer $limit  Límite de resultados
     * @return array Lista de eventos
     */
    public function getUserHistory(int $userId, int $limit = 20): array
    {
        $sql = 'SELECT *
                FROM auth_audit_logs
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Obtiene la IP real del cliente
     *
     * @return null|scalar|string[]
     *
     * @psalm-return non-empty-list<string>|null|scalar
     */
    private static function getClientIp()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (\str_contains($ip, ',')) {
                    $ip = \trim(\explode(',', $ip)[0]);
                }
                if (\filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Extrae nombre del dispositivo del User Agent
     */
    private static function parseDeviceName(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        // Detectar dispositivo móvil
        if (\preg_match('/(iPhone|iPad|Android|Mobile)/i', $userAgent, $matches)) {
            return $matches[1];
        }

        // Detectar navegador
        if (\preg_match('/(Chrome|Firefox|Safari|Edge|Opera)/i', $userAgent, $matches)) {
            return $matches[1];
        }

        return 'Unknown';
    }

    /**
     * Limpia logs antiguos (mantenimiento)
     *
     * @param integer $daysToKeep Días a mantener
     * @return integer Número de registros eliminados
     */
    public function cleanup(int $daysToKeep = 180): int
    {
        $sql = 'DELETE FROM auth_audit_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $daysToKeep]);

        return $stmt->rowCount();
    }
}
