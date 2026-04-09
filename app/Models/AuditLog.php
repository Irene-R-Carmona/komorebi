<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modelo AuditLog
 *
 * Gestiona logs de auditoría para acciones críticas del sistema.
 * Registra quién hizo qué, cuándo, desde dónde y qué cambió.
 */
final class AuditLog
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Registra una acción en el log de auditoría
     *
     * @param string       $action       Acción realizada (ej: 'create', 'update', 'delete', 'toggle_status')
     * @param string|null  $resourceType Tipo de recurso (ej: 'user', 'cafe', 'product')
     * @param integer|null $resourceId   ID del recurso afectado
     * @param array|null   $oldValues    Valores anteriores (para updates)
     * @param array|null   $newValues    Valores nuevos
     * @param integer|null $userId       ID del usuario que realiza la acción
     * @return integer ID del log creado
     */
    public static function log(
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): int {
        $db = Database::getConnection();

        // Obtener información del request
        $ipAddress = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Si no se proporciona userId, intentar obtenerlo de la sesión
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }

        // Preparar valores JSON
        $oldValuesJson = $oldValues ? \json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newValuesJson = $newValues ? \json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

        $sql = 'INSERT INTO audit_logs (
                    user_id, action, resource_type, resource_id,
                    old_values, new_values, ip_address, user_agent
                ) VALUES (
                    :user_id, :action, :resource_type, :resource_id,
                    :old_values, :new_values, :ip_address, :user_agent
                )';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValuesJson,
            'new_values' => $newValuesJson,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Obtiene logs de auditoría con filtros
     *
     * @param array   $filters Filtros opcionales: user_id, action, resource_type, date_from, date_to
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
            $where[] = 'al.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        // Filtro por acción
        if (!empty($filters['action'])) {
            $where[] = 'al.action = :action';
            $params['action'] = $filters['action'];
        }

        // Filtro por tipo de recurso
        if (!empty($filters['resource_type'])) {
            $where[] = 'al.resource_type = :resource_type';
            $params['resource_type'] = $filters['resource_type'];
        }

        // Filtro por fecha desde
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        // Filtro por fecha hasta
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        // Búsqueda por IP
        if (!empty($filters['ip_address'])) {
            $where[] = 'al.ip_address LIKE :ip_address';
            $params['ip_address'] = '%' . $filters['ip_address'] . '%';
        }

        $whereClause = \implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM audit_logs al WHERE $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos con JOIN a users
        $sql = "SELECT
                    al.id,
                    al.user_id,
                    al.action,
                    al.resource_type,
                    al.resource_id,
                    al.old_values,
                    al.new_values,
                    al.ip_address,
                    al.user_agent,
                    al.created_at,
                    u.name as user_name,
                    u.email as user_email
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $logs = $stmt->fetchAll();

        // Decodificar JSON
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? \json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? \json_decode($log['new_values'], true) : null;
        }

        return [
            'data' => $logs,
            'total' => $total,
        ];
    }

    /**
     * Obtiene estadísticas de auditoría
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

        // Total de acciones
        $sql = "SELECT
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    SUM(CASE WHEN created_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END) as last_24h,
                    SUM(CASE WHEN action IN ('delete', 'force_delete', 'bulk_delete', 'restore', 'deactivate') THEN 1 ELSE 0 END) as critical_actions
                FROM audit_logs
                WHERE $whereClause";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $totals = $stmt->fetch();

        // Acciones más frecuentes
        $sql = "SELECT action, COUNT(*) as count
                FROM audit_logs
                WHERE {$whereClause}
                GROUP BY action
                ORDER BY count DESC
                LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $topActions = $stmt->fetchAll();

        // Recursos más modificados
        $sql = "SELECT resource_type, COUNT(*) as count
                FROM audit_logs
                WHERE $whereClause AND resource_type IS NOT NULL
                GROUP BY resource_type
                ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $topResources = $stmt->fetchAll();

        return [
            'totals' => $totals,
            'top_actions' => $topActions,
            'top_resources' => $topResources,
        ];
    }

    /**
     * Obtiene historial de cambios de un recurso específico
     *
     * @param string  $resourceType Tipo de recurso
     * @param integer $resourceId   ID del recurso
     * @return array Lista de cambios
     */
    public function getResourceHistory(string $resourceType, int $resourceId): array
    {
        $sql = 'SELECT
                    al.*,
                    u.name as user_name,
                    u.email as user_email
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.resource_type = :resource_type
                  AND al.resource_id = :resource_id
                ORDER BY al.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        $logs = $stmt->fetchAll();

        // Decodificar JSON
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? \json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? \json_decode($log['new_values'], true) : null;
        }

        return $logs;
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
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Si hay múltiples IPs separadas por coma, tomar la primera
                if (\str_contains($ip, ',')) {
                    $ip = \trim(\explode(',', $ip)[0]);
                }
                // Validar que sea una IP válida
                if (\filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Limpia logs antiguos (mantenimiento)
     *
     * @param integer $daysToKeep Días a mantener
     * @return integer Número de registros eliminados
     */
    public function cleanup(int $daysToKeep = 365): int
    {
        $sql = 'DELETE FROM audit_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $daysToKeep]);

        return $stmt->rowCount();
    }
}
