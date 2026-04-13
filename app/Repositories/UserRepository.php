<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\UserRepositoryInterface;
use PDO;

/**
 * Repositorio de Usuarios.
 *
 * Encapsula toda la lógica de acceso a datos de usuarios,
 * incluyendo búsquedas por email, roles y gestión de perfil.
 */
class UserRepository extends AbstractRepository implements UserRepositoryInterface
{
    #[\Override]
    protected function getTable(): string
    {
        return 'users';
    }

    #[\Override]
    protected function getSelectFields(): array
    {
        return [
            'id',
            'uuid',
            'name',
            'email',
            'avatar',
            'created_at',
            'is_active',
            'deleted_at',
            'email_verified_at',
            'cafe_id',
            'preferences',
        ];
    }

    /**
     * Sobrescribir create() para generar UUID automáticamente.
     *
     * @param array<string, mixed> $data
     * @return int User ID insertado
     */
    #[\Override]
    public function create(array $data): int
    {
        // Generar UUID si no viene en $data
        if (!isset($data['uuid'])) {
            $data['uuid'] = $this->generateUuid();
        }

        // Llamar al create() del padre
        return parent::create($data);
    }

    /**
     * Generar UUID v4 compatible con MySQL CHAR(36).
     */
    private function generateUuid(): string
    {
        // Formato: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        // donde x es hexadecimal random y y es [8, 9, a, b]
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versión 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante RFC4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Buscar usuario por email.
     */
    public function findByEmail(string $email): ?array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => strtolower(trim($email))]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Buscar usuario por email incluyendo campos de credenciales de autenticación.
     * Usar SOLO en contextos de autenticación (login, rate limiting).
     */
    public function findByEmailWithCredentials(string $email): ?array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id, uuid, email, password, login_attempts, locked_until,
                    last_ip_address, is_active, email_verified_at
             FROM users
             WHERE email = :email
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['email' => strtolower(trim($email))]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Buscar usuario por ID incluyendo campos de seguridad.
     * Usar SOLO en operaciones de seguridad (cambio de contraseña, bloqueo de cuenta).
     */
    public function findByIdForSecurity(int $id): ?array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id, uuid, email, password, login_attempts, locked_until, last_ip_address
             FROM users
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Verificar si un email ya existe.
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT 1 FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => strtolower(trim($email))]);

        return (bool) $stmt->fetch();
    }

    /**
     * Obtener roles de un usuario.
     */
    public function getRoles(int $userId): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT r.name, r.code AS slug, r.description
             FROM roles r
             INNER JOIN user_roles ur ON r.id = ur.role_id
             WHERE ur.user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener permisos de un usuario (a través de sus roles).
     */
    public function getPermissions(int $userId): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT DISTINCT p.name, p.code AS slug, p.resource, p.action
             FROM permissions p
             INNER JOIN role_permissions rp ON p.id = rp.permission_id
             INNER JOIN user_roles ur ON rp.role_id = ur.role_id
             WHERE ur.user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comprueba si un usuario tiene un permiso específico.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT 1
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id
               AND (p.code = :perm OR p.name = :perm)
             LIMIT 1"
        );

        $stmt->execute(['user_id' => $userId, 'perm' => $permission]);

        return (bool) $stmt->fetch();
    }

    /**
     * Establece el estado activo/inactivo de una cuenta.
     */
    public function setActive(int $id, bool $active): bool
    {
        return $this->update($id, [
            'is_active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Asignar rol a un usuario.
     */
    public function assignRole(int $userId, int $roleId): bool
    {
        $stmt = $this->getDb()->prepare(
            "INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at)
             VALUES (:user_id, :role_id, NOW())"
        );

        return $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    /**
     * Remover rol de un usuario.
     */
    public function removeRole(int $userId, int $roleId): bool
    {
        $stmt = $this->getDb()->prepare(
            "DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id"
        );

        return $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    /**
     * Actualizar último login.
     */
    public function updateLastLogin(int $id, string $ipAddress): bool
    {
        return $this->update($id, [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip_address' => $ipAddress,
            'login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Incrementar intentos fallidos de login.
     */
    public function incrementFailedAttempts(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE users
             SET login_attempts = login_attempts + 1,
                 updated_at = NOW()
             WHERE id = :id"
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Bloquear usuario temporalmente.
     */
    public function lockAccount(int $id, int $minutes = 15): bool
    {
        $lockedUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));

        return $this->update($id, [
            'locked_until' => $lockedUntil,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Activar/desactivar cuenta.
     */
    public function toggleStatus(int $id): bool
    {
        $user = $this->findById($id);

        if (!$user) {
            return false;
        }

        return $this->update($id, [
            'is_active' => !$user['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Actualizar contraseña (hashea internamente con bcrypt).
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        return $this->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Verificar email.
     */
    public function verifyEmail(int $id): bool
    {
        return $this->update($id, [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Actualizar avatar.
     */
    public function updateAvatar(int $id, string $avatarUrl): bool
    {
        return $this->update($id, [
            'avatar' => $avatarUrl,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Buscar usuarios por rol.
     */
    public function findByRole(string $roleSlug): array
    {
        $fields = implode(', u.', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT u.{$fields}
             FROM users u
             INNER JOIN user_roles ur ON u.id = ur.user_id
             INNER JOIN roles r ON ur.role_id = r.id
             WHERE r.slug = :role_slug
             AND u.is_active = 1
             AND u.deleted_at IS NULL
             ORDER BY u.name"
        );
        $stmt->execute(['role_slug' => $roleSlug]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Soft delete (RGPD compliance).
     */
    #[\Override]
    public function softDelete(int $id): bool
    {
        return $this->update($id, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'email' => 'deleted_' . $id . '@deleted.local',
            'name' => 'Usuario Eliminado',
            'is_active' => 0,
        ]);
    }

    /**
     * Actualizar preferencias del usuario (JSON).
     */
    public function updatePreferences(int $id, array $preferences): bool
    {
        $json = json_encode($preferences, JSON_UNESCAPED_UNICODE);

        $stmt = $this->getDb()->prepare(
            "UPDATE users
             SET preferences = :prefs,
                 updated_at = NOW()
             WHERE id = :id"
        );

        return $stmt->execute([
            'id' => $id,
            'prefs' => $json,
        ]);
    }

    /**
     * Anonimizar usuario (alternativa GDPR-friendly).
     * Mantiene el registro pero elimina datos personales.
     */
    public function anonymize(int $id): bool
    {
        $anonymousEmail = 'deleted_' . $id . '@anonymous.local';

        $stmt = $this->getDb()->prepare(
            "UPDATE users SET
                name = 'Usuario Eliminado',
                email = :email,
                password = '',
                avatar = NULL,
                preferences = NULL,
                is_active = 0,
                deleted_at = NOW(),
                updated_at = NOW()
             WHERE id = :id"
        );

        return $stmt->execute(['id' => $id, 'email' => $anonymousEmail]);
    }

    /**
     * Obtener lista simplificada de usuarios activos (para dropdowns/filtros).
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    public function getActiveUsersList(): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id, name, email FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name"
        );
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todo el staff de un café con sus roles concatenados.
     *
     * @return array<int, array{id: int, name: string, email: string, is_active: int, created_at: string, roles: string}>
     */
    public function getStaffByCafe(int $cafeId): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT u.id, u.name, u.email, u.is_active, u.created_at,
                    GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.cafe_id = :cafe_id
               AND u.deleted_at IS NULL
             GROUP BY u.id, u.name, u.email, u.is_active, u.created_at
             ORDER BY u.name ASC"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener un staff member específico de un café, con todos sus campos y roles.
     * Devuelve null si no existe o no pertenece al café.
     *
     * @return array<string, mixed>|null
     */
    public function getStaffById(int $userId, int $cafeId): ?array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT u.*, GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.id = :user_id
               AND u.cafe_id = :cafe_id
               AND u.deleted_at IS NULL
             GROUP BY u.id"
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Comprobar si un usuario pertenece a un café dado (no borrado).
     */
    public function existsInCafe(int $userId, int $cafeId): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id FROM users WHERE id = :user_id AND cafe_id = :cafe_id AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        return (bool) $stmt->fetch();
    }

    /**
     * Obtener nombre e id de un staff member si pertenece al café.
     * Retorna null si no existe o no pertenece.
     *
     * @return array{id: int, name: string}|null
     */
    public function getStaffBasicById(int $userId, int $cafeId): ?array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id, name FROM users WHERE id = :user_id AND cafe_id = :cafe_id AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
