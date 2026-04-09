<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Modelo Permission
 *
 * Gestiona permisos del sistema.
 * Tabla: permissions (id, code, name, description, resource, action, created_at)
 */
final class Permission
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene todos los permisos.
     *
     * @return array<array{id: int, code: string, name: string, resource: ?string, action: ?string}>
     */
    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT id, code, name, description, resource, action FROM permissions ORDER BY code'
        );

        return $stmt->fetchAll();
    }

    /**
     * Obtiene un permiso por ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, description, resource, action FROM permissions WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Obtiene un permiso por código.
     */
    public function findByKey(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, description, resource, action FROM permissions WHERE code = :code'
        );
        $stmt->execute(['code' => $code]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Obtiene permisos por recurso.
     *
     * @return array<array{id: int, code: string, name: string, action: ?string}>
     */
    public function findByResource(string $resource): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, action FROM permissions WHERE resource = :resource ORDER BY action'
        );
        $stmt->execute(['resource' => $resource]);

        return $stmt->fetchAll();
    }

    /**
     * Crea un permiso.
     */
    public function create(
        string $code,
        string $name,
        ?string $description = null,
        ?string $resource = null,
        ?string $action = null
    ): int {
        // Validar que code no exista
        if ($this->findByKey($code) !== null) {
            throw new RuntimeException("El permiso '$code' ya existe.");
        }

        $stmt = $this->db->prepare(
            'INSERT INTO permissions (code, name, description, resource, action)
             VALUES (:code, :name, :description, :resource, :action)'
        );

        $stmt->execute([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'resource' => $resource,
            'action' => $action,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza un permiso.
     */
    public function update(
        int $id,
        ?string $name = null,
        ?string $description = null
    ): bool {
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

        $sql = 'UPDATE permissions SET ' . \implode(', ', $fields) . ' WHERE id = :id';

        return $this->db->prepare($sql)->execute($params);
    }

    /**
     * Elimina un permiso.
     */
    public function delete(int $id): bool
    {
        return $this->db->prepare('DELETE FROM permissions WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Obtiene roles que tienen un permiso.
     *
     * @return array<array{id: int, code: string, name: string}>
     */
    public function getRoles(int $permissionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id, r.code, r.name FROM roles r
             JOIN role_permissions rp ON r.id = rp.role_id
             WHERE rp.permission_id = :permission_id
             ORDER BY r.code'
        );
        $stmt->execute(['permission_id' => $permissionId]);

        return $stmt->fetchAll();
    }
}
