<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Exceptions\ValidationException;
use App\Models\Traits\HasUuid;
use Exception;
use PDO;
use Random\RandomException;

/**
 * Modelo User
 *
 * Gestiona todas las opfinal final final eraciones de datos relacionadas con usuarios.
 *
 * Seguridad:
 * - Passwords hasheados con ARGON2ID
 * - Emails normalizados (lowercase)
 * - Bloqueo tras intentos fallidos
 * - Prepared statements en todas las queries
 */
class User
{
    use HasUuid;

    private PDO $db;

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Intentos máximos antes de bloqueo temporal */
    public const int MAX_LOGIN_ATTEMPTS = 5;

    /** Minutos de bloqueo tras superar intentos */
    public const int LOCKOUT_MINUTES = 15;

    /** Roles válidos del sistema (canónicos) */
    public const array VALID_ROLES = ['user', 'reception', 'kitchen', 'keeper', 'manager', 'supervisor', 'admin'];

    /** Campos seleccionables (evita SELECT *) */
    private const array SELECT_FIELDS = [
        'id',
        'uuid',
        'name',
        'email',
        'password',
        'is_active',
        'cafe_id',
        'avatar',
        'preferences',
        'login_attempts',
        'locked_until',
        'deleted_at',
        'anonymized_at',
        'created_at',
        'updated_at',
    ];

    /** Campos públicos (sin datos sensibles) */
    private const array PUBLIC_FIELDS = [
        'id',
        'uuid',
        'name',
        'email',
        'role',
        'is_active',
        'cafe_id',
        'avatar',
        'created_at',
    ];

    // ─────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    // Búsqueda
    // ─────────────────────────────────────────────────────────────

    /**
     * Busca usuario por ID.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $stmt = $this->db->prepare(
            "SELECT $fields FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (array) $row;
    }

    /**
     * Busca usuario por UUID (para URLs públicas).
     */
    public function findByUuid(string $uuid): ?array
    {
        if (!$this->isValidUuid($uuid)) {
            return null;
        }

        $fields = \implode(', ', self::PUBLIC_FIELDS);

        $stmt = $this->db->prepare(
            "SELECT $fields FROM users WHERE uuid = :uuid LIMIT 1"
        );
        $stmt->execute(['uuid' => $uuid]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (array) $row;
    }

    /**
     * Busca usuario por email (para login).
     * Incluye password para verificación.
     */
    public function findByEmail(string $email): ?array
    {
        $email = $this->normalizeEmail($email);

        $fields = \implode(', ', self::SELECT_FIELDS);

        $stmt = $this->db->prepare(
            "SELECT $fields FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : (array) $row;
    }

    /**
     * Verifica si un email ya está registrado.
     */
    public function emailExists(string $email): bool
    {
        $email = $this->normalizeEmail($email);

        $stmt = $this->db->prepare(
            'SELECT 1 FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetch();
    }

    // ─────────────────────────────────────────────────────────────
    // Creación
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo usuario.
     *
     * @param array{name: string, email: string, password: string, cafe_id?: int} $data
     * @return integer ID del usuario creado
     * @throws RandomException
     * @throws ValidationException
     */
    public function create(array $data): int
    {
        // Validar datos requeridos
        $this->validateRequired($data, ['name', 'email', 'password']);

        $name = $this->sanitizeName($data['name']);
        $email = $this->normalizeEmail($data['email']);
        $cafeId = $data['cafe_id'] ?? null;

        // Validar email único
        if ($this->emailExists($email)) {
            throw ValidationException::withMessage('El email ya está registrado.');
        }

        // Hash seguro
        $hash = \password_hash($data['password'], PASSWORD_ARGON2ID);
        $uuid = $this->generateUuid();

        $sql = 'INSERT INTO users (uuid, name, email, password, cafe_id)
                VALUES (:uuid, :name, :email, :password, :cafe_id)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'uuid' => $uuid,
            'name' => $name,
            'email' => $email,
            'password' => $hash,
            'cafe_id' => $cafeId,
        ]);

        $userId = (int) $this->db->lastInsertId();

        // Asignar rol 'user' por defecto mediante RBAC puro
        try {
            $this->assignDefaultRole($userId);
        } catch (Exception $e) {
            Logger::error("Error asignando rol default a usuario $userId: " . $e->getMessage(), ['user_id' => $userId, 'exception' => $e->getMessage()]);
            // No fallar si no se asigna rol, continuar
        }

        return $userId;
    }

    /**
     * Asigna rol 'user' por defecto a nuevo usuario.
     * @throws ValidationException
     */
    private function assignDefaultRole(int $userId): void
    {
        // Obtener ID del rol 'user'
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE code = :code');
        $stmt->execute(['code' => 'user']);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            throw ValidationException::withMessage('Rol "user" no encontrado en el sistema.');
        }

        // Insertar en user_roles
        $stmt = $this->db->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'role_id' => (int) $role['id'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Actualización
    // ─────────────────────────────────────────────────────────────

    /**
     * Actualiza datos del perfil.
     *
     * @param integer $id   ID del usuario
     * @param array   $data Campos a actualizar (name, email)
     * @return boolean
     * @throws ValidationException
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id];

        // Solo actualizar campos permitidos
        if (isset($data['name'])) {
            $sets[] = 'name = :name';
            $params['name'] = $this->sanitizeName($data['name']);
        }

        if (isset($data['email'])) {
            $newEmail = $this->normalizeEmail($data['email']);

            // Verificar que no esté en uso por otro usuario
            $stmt = $this->db->prepare(
                'SELECT id FROM users WHERE email = :email AND id != :check_id LIMIT 1'
            );
            $stmt->execute(['email' => $newEmail, 'check_id' => $id]);

            if ($stmt->fetch()) {
                throw ValidationException::withMessage('El email ya está en uso.');
            }

            $sets[] = 'email = :email';
            $params['email'] = $newEmail;
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE users SET ' . \implode(', ', $sets) . ' WHERE id = :id';

        return $this->db->prepare($sql)->execute($params);
    }

    /**
     * Actualiza la contraseña.
     */
    public function updatePassword(int $id, string $newPassword): bool
    {
        $hash = \password_hash($newPassword, PASSWORD_ARGON2ID);

        $stmt = $this->db->prepare(
            'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'password' => $hash,
        ]);
    }

    /**
     * Actualiza el avatar.
     *
     * @param integer $id       ID del usuario
     * @param string  $filename Nombre del archivo guardado
     */
    public function updateAvatar(int $id, string $filename): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET avatar = :avatar, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'avatar' => $filename,
        ]);
    }

    /**
     * Actualiza preferencias del usuario (JSON).
     *
     * @param integer $id          ID del usuario
     * @param array   $preferences Array de preferencias
     */
    public function updatePreferences(int $id, array $preferences): bool
    {
        $json = \json_encode($preferences, JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare(
            'UPDATE users SET preferences = :prefs, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'prefs' => $json,
        ]);
    }

    /**
     * Verifica el email del usuario (marcar como verificado).
     */
    public function verifyEmail(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Establece el campo is_active para activar/desactivar usuarios.
     */
    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :active, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0]);
    }

    /**
     * Actualiza la preferencia de tema circadiano en columna dedicada.
     * Si la columna no existe (entorno previo), la consulta fallará y se lanzará excepción.
     * @param int $id
     * @param string|null $value
     * @return bool
     */
    // Eliminado: persistencia de preferencia de tema para paleta circadiana (funcionalidad eliminada)

    // ─────────────────────────────────────────────────────────────
    // Autenticación y Seguridad
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifica la contraseña de un usuario.
     * Incluye rehash automático si el algoritmo cambió.
     */
    public function verifyPassword(array $user, string $password): bool
    {
        if (!isset($user['password'], $user['id'])) {
            return false;
        }

        if (!\password_verify($password, $user['password'])) {
            return false;
        }

        // Rehash si es necesario (ej: migración de bcrypt a argon2id)
        if (\password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
            $this->updatePassword((int) $user['id'], $password);
        }

        return true;
    }

    /**
     * Verifica si el usuario está bloqueado.
     */
    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }

        return \strtotime($user['locked_until']) > \time();
    }

    /**
     * Obtiene los minutos restantes de bloqueo.
     */
    public function lockoutMinutesRemaining(array $user): int
    {
        if (!$this->isLocked($user)) {
            return 0;
        }

        $remaining = \strtotime($user['locked_until']) - \time();

        return (int) \ceil($remaining / 60);
    }

    /**
     * Registra un intento de login fallido.
     * Bloquea la cuenta si se superan los intentos máximos.
     */
    public function registerFailedAttempt(int $id): void
    {
        // Incrementar contador
        $stmt = $this->db->prepare(
            'UPDATE users SET login_attempts = login_attempts + 1 WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        // Verificar si hay que bloquear
        $user = $this->findById($id);

        if ($user && (int) $user['login_attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
            $lockUntil = \date('Y-m-d H:i:s', \strtotime('+' . self::LOCKOUT_MINUTES . ' minutes'));

            $stmt = $this->db->prepare(
                'UPDATE users SET locked_until = :locked WHERE id = :id'
            );
            $stmt->execute(['id' => $id, 'locked' => $lockUntil]);
        }
    }

    /**
     * Resetea los intentos de login tras login exitoso.
     */
    public function clearLoginAttempts(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    // ─────────────────────────────────────────────────────────────
    // Administración
    // ─────────────────────────────────────────────────────────────

    /**
     * Lista todos los usuarios (paginado).
     *
     * @param integer     $page    Página actual (1-indexed)
     * @param integer     $perPage Usuarios por página
     * @param string|null $role    Filtrar por rol
     * @param string|null $search  Búsqueda por nombre/email
     * @return array{data: array, total: int, pages: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        ?string $role = null,
        ?string $search = null
    ): array {
        $fields = \implode(', ', self::PUBLIC_FIELDS);
        $where = [];
        $params = [];

        if ($role !== null) {
            $where[] = 'role = :role';
            $params['role'] = $role;
        }

        if ($search !== null) {
            $where[] = '(name LIKE :search OR email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereClause = $where ? 'WHERE ' . \implode(' AND ', $where) : '';

        // Contar total
        $countSql = "SELECT COUNT(*) FROM users $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Calcular paginación
        $pages = (int) \ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // Obtener datos
        $sql = "SELECT $fields FROM users $whereClause
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        // Bind de parámetros (LIMIT/OFFSET necesitan bindValue con tipo)
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Activa o desactiva un usuario.
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Cambia el rol de un usuario.
     * @throws ValidationException
     */
    public function updateRole(int $id, string $role): bool
    {
        if (!\in_array($role, self::VALID_ROLES, true)) {
            throw ValidationException::withMessage('Rol inválido.');
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'role' => $role]);
    }

    /**
     * Asigna un café a un usuario (para staff).
     */
    public function assignCafe(int $userId, ?int $cafeId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET cafe_id = :cafe_id, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute(['id' => $userId, 'cafe_id' => $cafeId]);
    }

    /**
     * Obtiene los roles de un usuario (RBAC puro).
     *
     * @return array<array{id: int, code: string, name: string}>
     */
    public function getRoles(int $userId): array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT r.id, r.code, r.name
                FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = :user_id
                ORDER BY r.name
            ');
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            Logger::error("[User::getRoles] Error obteniendo roles para user $userId: " . $e->getMessage(), ['user_id' => $userId, 'exception' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Cuenta usuarios por rol (RBAC puro).
     *
     * @param string $roleCode Código del rol (ej: 'admin', 'reception', 'kitchen')
     * @return integer Cantidad de usuarios con ese rol
     */
    public function countByRole(string $roleCode): int
    {
        try {
            $stmt = $this->db->prepare('
                SELECT COUNT(DISTINCT u.id) as count
                FROM users u
                INNER JOIN user_roles ur ON u.id = ur.user_id
                INNER JOIN roles r ON ur.role_id = r.id
                WHERE r.code = :role_code
                  AND u.is_active = 1
            ');
            $stmt->execute(['role_code' => $roleCode]);

            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            Logger::error("[User::countByRole] Error contando usuarios con rol $roleCode: " . $e->getMessage(), ['role_code' => $roleCode, 'exception' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Obtiene usuarios por rol (RBAC puro).
     *
     * @param string $roleCode Código del rol
     * @return array Lista de usuarios con ese rol
     */
    public function findByRole(string $roleCode): array
    {
        try {
            $fields = \implode(', ', self::PUBLIC_FIELDS);
            $stmt = $this->db->prepare("
                SELECT DISTINCT $fields
                FROM users u
                INNER JOIN user_roles ur ON u.id = ur.user_id
                INNER JOIN roles r ON ur.role_id = r.id
                WHERE r.code = :role_code
                  AND u.is_active = 1
                ORDER BY u.name
            ");
            $stmt->execute(['role_code' => $roleCode]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            Logger::error("[User::findByRole] Error obteniendo usuarios con rol $roleCode: " . $e->getMessage(), ['role_code' => $roleCode, 'exception' => $e->getMessage()]);

            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Eliminación (GDPR)
    // ─────────────────────────────────────────────────────────────

    /**
     * Elimina un usuario permanentemente.
     *
     * Nota: Para cumplimiento GDPR completo, considera:
     * - Anonimizar en lugar de eliminar cuando sea posible
     * - Eliminar/retener datos relacionados según la política de privacidad
     * - Registrar la eliminación con motivo y fecha
     */
    public function delete(int $id): bool
    {
        // Primero eliminar datos relacionados (o usar ON DELETE CASCADE)
        // $this->deleteRelatedData($id);

        return $this->db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Anonimiza un usuario (alternativa GDPR-friendly).
     * Mantiene el registro pero elimina datos personales.
     */
    public function anonymize(int $id): bool
    {
        $anonymousEmail = 'deleted_' . $id . '@anonymous.local';

        $stmt = $this->db->prepare(
            "UPDATE users SET
                name = 'Usuario Eliminado',
                email = :email,
                password = '',
                avatar = NULL,
                preferences = NULL,
                is_active = 0,
                updated_at = NOW()
             WHERE id = :id"
        );

        return $stmt->execute(['id' => $id, 'email' => $anonymousEmail]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Normaliza un email (lowercase, trim).
     */
    private function normalizeEmail(string $email): string
    {
        return \strtolower(\trim($email));
    }

    /**
     * Sanitiza un nombre (trim, limita longitud).
     */
    private function sanitizeName(string $name): string
    {
        $name = \trim($name);

        return \mb_substr($name, 0, 100); // Límite razonable
    }

    /**
     * Valida que existan campos requeridos.
     *
     * @param array $data
     * @param array $fields
     * @throws ValidationException
     */
    private function validateRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || \trim((string) $data[$field]) === '') {
                throw ValidationException::required($field);
            }
        }
    }
}
