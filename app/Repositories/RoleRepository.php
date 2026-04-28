<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\DTO\RoleDTO;
use App\Domain\Mappers\RoleMapper;
use App\Repositories\Contracts\RoleRepositoryInterface;
use PDO;

final class RoleRepository implements RoleRepositoryInterface
{
    private PDO $db;

    private RoleMapper $mapper;

    public function __construct(?PDO $db = null, ?RoleMapper $mapper = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->mapper = $mapper ?? new RoleMapper();
    }

    public function findAllWithCounts(): array
    {
        $stmt = $this->db->query('
            SELECT
                r.id,
                r.code,
                r.name,
                r.description,
                COUNT(DISTINCT rp.permission_id) as permissions_count,
                COUNT(DISTINCT ur.user_id) as users_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id, r.code, r.name, r.description
            ORDER BY r.name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllWithPermissions(): array
    {
        $stmt = $this->db->query("
            SELECT r.*,
                   GROUP_CONCAT(p.id                  ORDER BY p.code SEPARATOR ',') AS permission_ids,
                   GROUP_CONCAT(COALESCE(p.name, '') ORDER BY p.code SEPARATOR ',') AS permission_names
            FROM roles r
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            LEFT JOIN permissions p ON p.id = rp.permission_id
            GROUP BY r.id
            ORDER BY r.name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_map(static function (array $row): array {
            if ($row['permission_ids'] !== null) {
                $ids = \explode(',', (string) $row['permission_ids']);
                $names = \explode(',', (string) $row['permission_names']);

                $row['permissions'] = \array_map(
                    static fn(string $id, string $name): array => [
                        'id' => (int) $id,
                        'name' => $name,
                    ],
                    $ids,
                    $names
                );
            } else {
                $row['permissions'] = [];
            }

            unset($row['permission_ids'], $row['permission_names']);

            return $row;
        }, $rows);
    }

    public function getStats(): array
    {
        $stmt = $this->db->query('
            SELECT
                COUNT(DISTINCT ur.user_id) as users_with_roles,
                COUNT(DISTINCT r.id) as total_roles,
                COUNT(DISTINCT p.id) as total_permissions
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
        ');

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?RoleDTO
    {
        $stmt = $this->db->prepare('SELECT id, code, name, description FROM roles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapper->toDTO($row) : null;
    }

    public function findByCode(string $code): ?RoleDTO
    {
        $stmt = $this->db->prepare('SELECT id, code, name, description FROM roles WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapper->toDTO($row) : null;
    }

    public function create(string $code, string $name, ?string $description = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO roles (code, name, description) VALUES (:code, :name, :description)'
        );
        $stmt->execute(['code' => $code, 'name' => $name, 'description' => $description]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, ?string $name = null, ?string $description = null): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if ($name !== null) {
            $fields[] = 'name = :name';
            $params['name'] = $name;
        }
        if ($description !== null) {
            $fields[] = 'description = :description';
            $params['description'] = $description;
        }

        if (empty($fields)) {
            return false;
        }

        return $this->db->prepare(
            'UPDATE roles SET ' . \implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id'
        )->execute($params);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $id]);
    }

    public function countUsers(int $roleId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = :role_id');
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function grantPermission(int $roleId, int $permissionId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id'
        );
        $stmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);

        if ($stmt->fetch()) {
            return true;
        }

        return $this->db->prepare(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
        )->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    }

    public function revokePermission(int $roleId, int $permissionId): bool
    {
        return $this->db->prepare(
            'DELETE FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id'
        )->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    }

    public function findAllPermissions(): array
    {
        $stmt = $this->db->query(
            'SELECT id, code, name, description, resource, action FROM permissions ORDER BY code'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPermissionById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, description, resource, action FROM permissions WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
