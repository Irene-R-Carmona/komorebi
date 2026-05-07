<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Modelo Role
 *
 * Gestiona roles del sistema.
 * Tabla: roles (id, key, name, description, created_at, updated_at)
 */
final class Role
{
    private ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    /**
     * Obtiene todos los roles.
     *
     * @return array<array{id: int, code: string, name: string, description: ?string}>
     */
    public function all(): array
    {
        $stmt = $this->getDb()->query('SELECT id, code, name, description FROM roles ORDER BY name');

        return $stmt->fetchAll();
    }

    /**
     * Obtiene un rol por ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->getDb()->prepare('SELECT id, code, name, description FROM roles WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Obtiene un rol por código.
     */
    public function findByKey(string $code): ?array
    {
        $stmt = $this->getDb()->prepare('SELECT id, code, name, description FROM roles WHERE code = :code');
        $stmt->execute(['code' => $code]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Crea un rol.
     */
    public function create(string $code, string $name, ?string $description = null): int
    {
        // Validar que code no exista
        if ($this->findByKey($code) !== null) {
            throw new RuntimeException("El rol '$code' ya existe.");
        }

        $stmt = $this->getDb()->prepare(
            'INSERT INTO roles (code, name, description) VALUES (:code, :name, :description)'
        );

        $stmt->execute([
            'code' => $code,
            'name' => $name,
            'description' => $description,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    /**
     * Actualiza un rol.
     */
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

        $sql = 'UPDATE roles SET ' . \implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';

        return $this->getDb()->prepare($sql)->execute($params);
    }

    /**
     * Elimina un rol.
     */
    public function delete(int $id): bool
    {
        return $this->getDb()->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Obtiene permisos de un rol.
     *
     * @return array<array{id: int, code: string, name: string}>
     */
    public function getPermissions(int $roleId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT p.id, p.code, p.name FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id
             ORDER BY p.code'
        );
        $stmt->execute(['role_id' => $roleId]);

        return $stmt->fetchAll();
    }

    /**
     * Asigna permiso a rol.
     */
    public function grantPermission(int $roleId, int $permissionId): bool
    {
        // Verificar que no exista ya
        $stmt = $this->getDb()->prepare(
            'SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id'
        );
        $stmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);

        if ($stmt->fetch()) {
            return true; // Ya existe
        }

        $stmt = $this->getDb()->prepare(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
        );

        return $stmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    }

    /**
     * Revoca permiso de rol.
     */
    public function revokePermission(int $roleId, int $permissionId): bool
    {
        $stmt = $this->getDb()->prepare(
            'DELETE FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id'
        );

        return $stmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    }

    /**
     * Obtiene todos los roles con sus permisos en una única consulta.
     * Elimina el patrón N+1 al cargar permisos de todos los roles a la vez.
     *
     * Cada rol devuelto incluye 'permissions': array de ['id' => int, 'name' => string].
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllWithPermissions(): array
    {
        $sql = "
            SELECT r.*,
                   GROUP_CONCAT(p.id                  ORDER BY p.code SEPARATOR ',') AS permission_ids,
                   GROUP_CONCAT(COALESCE(p.name, '') ORDER BY p.code SEPARATOR ',') AS permission_names
            FROM roles r
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            LEFT JOIN permissions p ON p.id = rp.permission_id
            GROUP BY r.id
            ORDER BY r.name
        ";

        $stmt = $this->getDb()->query($sql);
        $rows = $stmt->fetchAll();

        return \array_map(static function (array $row): array {
            if ($row['permission_ids'] !== null) {
                $ids = \explode(',', (string) $row['permission_ids']);
                $names = \explode(',', (string) $row['permission_names']);

                $row['permissions'] = \array_map(
                    static fn (string $id, ?string $name): array => [
                        'id' => (int) $id,
                        'name' => $name ?? '',
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

    /**
     * Obtiene todos los roles con conteos de permisos y usuarios asignados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllWithCounts(): array
    {
        $sql = '
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
        ';

        $stmt = $this->getDb()->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene estadísticas del sistema RBAC.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $sql = '
            SELECT
                COUNT(DISTINCT ur.user_id) as users_with_roles,
                COUNT(DISTINCT r.id) as total_roles,
                COUNT(DISTINCT p.id) as total_permissions
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
        ';

        $stmt = $this->getDb()->query($sql);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta la cantidad de usuarios que tienen asignado el rol dado.
     */
    public function countUsers(int $roleId): int
    {
        $stmt = $this->getDb()->prepare('SELECT COUNT(*) as count FROM user_roles WHERE role_id = :role_id');
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
}
