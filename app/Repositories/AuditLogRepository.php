<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\DTO\AuditLogDTO;
use App\Domain\Mappers\AuditLogMapper;
use App\Models\AuditLog as AuditLogModel;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use PDO;

final class AuditLogRepository implements AuditLogRepositoryInterface
{
    private PDO $db;
    private AuditLogMapper $mapper;

    public function __construct(?PDO $db = null, ?AuditLogMapper $mapper = null)
    {
        $this->db     = $db ?? Database::getConnection();
        $this->mapper = $mapper ?? new AuditLogMapper();
    }

    public function findById(int $id): ?AuditLogDTO
    {
        $stmt = $this->db->prepare(
            'SELECT al.id, al.user_id, al.action, al.resource_type, al.resource_id,
                    al.old_values, al.new_values, al.ip_address, al.user_agent, al.created_at
             FROM audit_logs al
             WHERE al.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->mapper->toDTO($row) : null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$whereClause, $params] = $this->buildWhereClause($filters);

        $countSql = "SELECT COUNT(*) FROM audit_logs al WHERE {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql = "
            SELECT
                al.id, al.user_id, al.action, al.resource_type, al.resource_id,
                al.old_values, al.new_values, al.ip_address, al.user_agent, al.created_at,
                u.name  AS user_name,
                u.email AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? \json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? \json_decode($log['new_values'], true) : null;
        }

        return ['data' => $logs, 'total' => $total];
    }

    public function getStats(array $filters = []): array
    {
        [$whereClause, $params] = $this->buildDateWhereClause($filters);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                                                       AS total_actions,
                COUNT(DISTINCT user_id)                                                        AS unique_users,
                COUNT(DISTINCT ip_address)                                                     AS unique_ips,
                SUM(CASE WHEN created_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END)       AS last_24h,
                SUM(CASE WHEN action IN ('delete','force_delete','bulk_delete','restore','deactivate')
                         THEN 1 ELSE 0 END)                                                    AS critical_actions
            FROM audit_logs
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT action, COUNT(*) AS count
            FROM audit_logs WHERE {$whereClause}
            GROUP BY action ORDER BY count DESC LIMIT 10
        ");
        $stmt->execute($params);
        $topActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT resource_type, COUNT(*) AS count
            FROM audit_logs WHERE {$whereClause} AND resource_type IS NOT NULL
            GROUP BY resource_type ORDER BY count DESC
        ");
        $stmt->execute($params);
        $topResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'totals' => $totals,
            'top_actions' => $topActions,
            'top_resources' => $topResources,
        ];
    }

    public function getResourceHistory(string $resourceType, int $resourceId): array
    {
        $stmt = $this->db->prepare('
            SELECT al.*, u.name AS user_name, u.email AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.resource_type = :resource_type AND al.resource_id = :resource_id
            ORDER BY al.created_at DESC
        ');
        $stmt->execute(['resource_type' => $resourceType, 'resource_id' => $resourceId]);

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? \json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? \json_decode($log['new_values'], true) : null;
        }

        return $logs;
    }

    public function cleanup(int $daysToKeep = 365): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $stmt->execute(['days' => $daysToKeep]);

        return $stmt->rowCount();
    }

    /** @return array{string, array<string, mixed>} */
    private function buildWhereClause(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'al.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['resource_type'])) {
            $where[] = 'al.resource_type = :resource_type';
            $params['resource_type'] = $filters['resource_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['ip_address'])) {
            $where[] = 'al.ip_address LIKE :ip_address';
            $params['ip_address'] = '%' . $filters['ip_address'] . '%';
        }

        return [\implode(' AND ', $where), $params];
    }

    public function log(
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): int {
        return AuditLogModel::log($action, $resourceType, $resourceId, $oldValues, $newValues, $userId);
    }

    /** @return array{string, array<string, mixed>} */
    private function buildDateWhereClause(array $filters): array
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

        return [\implode(' AND ', $where), $params];
    }
}
