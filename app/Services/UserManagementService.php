<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Result;
use App\Core\TransactionalService;
use App\Models\User;
use App\Services\Contracts\UserManagementServiceInterface;
use Exception;
use PDO;

/**
 * Servicio de gestión de usuarios (CRUD)
 *
 * Encapsula la lógica de negocio relacionada con
 * la creación, actualización y eliminación de usuarios
 * desde el panel administrativo.
 *
 * @package Komorebi\Services
 */
final class UserManagementService extends TransactionalService implements UserManagementServiceInterface
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct(Database::getConnection());
        $this->userModel = new User();
    }

    /**
     * Obtiene todos los usuarios con sus roles
     *
     * @return array Lista de usuarios con roles
     */
    #[\Override]
    public function getUsersWithRoles(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    u.id,
                    u.uuid,
                    u.name,
                    u.email,
                    u.is_active,
                    u.created_at,
                    u.last_login,
                    GROUP_CONCAT(r.name SEPARATOR ', ') as roles,
                    GROUP_CONCAT(r.id) as role_ids
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                GROUP BY u.id, u.uuid, u.name, u.email, u.is_active, u.created_at, u.last_login
                ORDER BY u.created_at DESC
            ");

            if ($stmt === false) {
                return [];
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Registrar el error para diagnóstico y devolver lista vacía para evitar 500
            if (function_exists('error_log')) {
                error_log('[UserManagementService] getUsersWithRoles failed: ' . $e->getMessage());
            }

            return [];
        }
    }

    /**
     * Valida datos de usuario
     *
     * @param array   $data     Datos del usuario
     * @param boolean $isUpdate Si es actualización (password opcional)
     * @return Result Retorna errores en data['errors'] si falla
     */
    #[\Override]
    public function validateUserData(array $data, bool $isUpdate = false): Result
    {
        $errors = [];

        // Validar nombre
        if (empty($data['name']) || \strlen($data['name']) < 2 || \strlen($data['name']) > 100) {
            $errors['name'] = 'El nombre debe tener entre 2 y 100 caracteres';
        }

        // Validar email
        if (empty($data['email']) || !\filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido';
        }

        // Validar password (solo si no es update o si se proporciona)
        if ((!$isUpdate || !empty($data['password'])) && (empty($data['password']) || \strlen($data['password']) < 8)) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
        }

        // Validar rol
        if (empty($data['role_id']) || !\is_numeric($data['role_id'])) {
            $errors['role_id'] = 'Debe seleccionar un rol válido';
        }

        if (!empty($errors)) {
            return Result::fail('Datos de usuario inválidos', 'validation', ['errors' => $errors]);
        }

        return Result::ok();
    }

    /**
     * Crea un nuevo usuario
     *
     * @param array $data Datos del usuario
     * @return Result ID del usuario creado
     */
    #[\Override]
    public function createUser(array $data): Result
    {
        // Validar datos
        $validation = $this->validateUserData($data, false);
        if ($validation->isFail()) {
            return $validation;
        }

        // Verificar si el email ya existe
        $existingUser = $this->userModel->findByEmail($data['email']);
        if ($existingUser) {
            return Result::fail('El email ya está registrado');
        }

        return $this->transact(function () use ($data): Result {
            // Crear usuario
            $stmt = $this->db->prepare('
                INSERT INTO users (name, email, password, is_active, created_at)
                VALUES (:name, :email, :password, 1, NOW())
            ');

            $stmt->execute([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => \password_hash($data['password'], PASSWORD_ARGON2ID),
            ]);

            $userId = (int) $this->db->lastInsertId();

            // Asignar rol
            $this->assignRole($userId, (int) $data['role_id']);

            return Result::ok(['id' => $userId, 'message' => 'Usuario creado exitosamente']);
        });
    }

    /**
     * Actualiza un usuario existente
     *
     * @param integer $userId ID del usuario
     * @param array   $data   Datos actualizados
     * @return Result
     */
    #[\Override]
    public function updateUser(int $userId, array $data): Result
    {
        // Validar datos
        $validation = $this->validateUserData($data, true);
        if ($validation->isFail()) {
            return $validation;
        }

        // Verificar que el usuario existe
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return Result::fail('Usuario no encontrado');
        }

        // Verificar si el email ya existe en otro usuario
        if ($data['email'] !== $user['email']) {
            $existingUser = $this->userModel->findByEmail($data['email']);
            if ($existingUser && $existingUser['id'] !== $userId) {
                return Result::fail('El email ya está registrado');
            }
        }

        return $this->transact(function () use ($userId, $data): Result {
            // Preparar query de actualización
            if (!empty($data['password'])) {
                // Actualizar con nueva contraseña
                $stmt = $this->db->prepare('
                    UPDATE users
                    SET name = :name, email = :email, password = :password
                    WHERE id = :id
                ');

                $stmt->execute([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => \password_hash($data['password'], PASSWORD_ARGON2ID),
                    'id' => $userId,
                ]);
            } else {
                // Actualizar sin cambiar contraseña
                $stmt = $this->db->prepare('
                    UPDATE users
                    SET name = :name, email = :email
                    WHERE id = :id
                ');

                $stmt->execute([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'id' => $userId,
                ]);
            }

            // Actualizar rol si cambió
            if (!empty($data['role_id'])) {
                // Eliminar roles actuales
                $stmt = $this->db->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
                $stmt->execute(['user_id' => $userId]);

                // Asignar nuevo rol
                $this->assignRole($userId, (int) $data['role_id']);
            }

            return Result::ok(['ok' => true, 'message' => 'Usuario actualizado exitosamente']);
        });
    }

    /**
     * Desactiva un usuario (soft delete)
     *
     * @param integer $userId ID del usuario
     * @return Result
     */
    #[\Override]
    public function deactivateUser(int $userId): Result
    {
        try {
            $stmt = $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
            $stmt->execute(['id' => $userId]);

            return Result::ok(['ok' => true, 'message' => 'Usuario desactivado exitosamente']);
        } catch (Exception $e) {
            return Result::fail('Error al desactivar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Alterna el estado activo/inactivo de un usuario
     *
     * @param integer $userId ID del usuario
     * @return Result
     */
    #[\Override]
    public function toggleUserStatus(int $userId): Result
    {
        try {
            // Obtener estado actual
            $stmt = $this->db->prepare('SELECT is_active FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return Result::fail('Usuario no encontrado');
            }

            // Alternar estado
            $newStatus = $user['is_active'] ? 0 : 1;

            $stmt = $this->db->prepare('UPDATE users SET is_active = :status WHERE id = :id');
            $stmt->execute([
                'status' => $newStatus,
                'id' => $userId,
            ]);

            $statusText = $newStatus ? 'activado' : 'desactivado';

            return Result::ok([
                'is_active' => (bool) $newStatus,
                'message' => "Usuario $statusText exitosamente",
            ]);
        } catch (Exception $e) {
            return Result::fail('Error al cambiar estado: ' . $e->getMessage());
        }
    }

    /**
     * Asigna un rol a un usuario
     *
     * @param integer $userId ID del usuario
     * @param integer $roleId ID del rol
     * @return void
     */
    private function assignRole(int $userId, int $roleId): void
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO user_roles (user_id, role_id, assigned_at)
                VALUES (:user_id, :role_id, NOW())
            ');

            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);

            return;
        } catch (Exception) {
            return;
        }
    }
}
