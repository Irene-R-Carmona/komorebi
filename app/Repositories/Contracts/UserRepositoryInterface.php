<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\UserDTO;
use App\Repositories\RepositoryInterface;

/**
 * Interfaz del repositorio de usuarios.
 *
 * Define operaciones de acceso a datos específicas de la tabla users,
 * incluyendo autenticación, roles, permisos y gestión de perfil.
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?UserDTO;

    /**
     * Buscar un usuario por su email.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Buscar usuario por email incluyendo credenciales de autenticación.
     * Usar SOLO en contextos de autenticación (login, rate limiting).
     *
     * @return array<string, mixed>|null
     */
    public function findByEmailWithCredentials(string $email): ?array;

    /**
     * Buscar usuario por ID incluyendo campos de seguridad.
     * Usar SOLO en operaciones de seguridad (cambio de contraseña, bloqueo de cuenta).
     *
     * @return array<string, mixed>|null
     */
    public function findByIdForSecurity(int $id): ?array;

    /**
     * Verificar si existe un email registrado.
     */
    public function emailExists(string $email): bool;

    /**
     * Obtener los roles asignados a un usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRoles(int $userId): array;

    /**
     * Obtener todos los permisos efectivos de un usuario (vía roles).
     *
     * @return array<int, string>
     */
    public function getPermissions(int $userId): array;

    /**
     * Verificar si un usuario tiene un permiso concreto.
     */
    public function hasPermission(int $userId, string $permission): bool;

    /**
     * Activar o desactivar una cuenta de usuario.
     */
    public function setActive(int $id, bool $active): bool;

    /**
     * Asignar un rol a un usuario.
     */
    public function assignRole(int $userId, int $roleId): bool;

    /**
     * Quitar un rol a un usuario.
     */
    public function removeRole(int $userId, int $roleId): bool;

    /**
     * Registrar el último login con su IP.
     */
    public function updateLastLogin(int $id, string $ipAddress): bool;

    /**
     * Incrementar los intentos fallidos de autenticación.
     */
    public function incrementFailedAttempts(int $id): bool;

    /**
     * Bloquear la cuenta durante un número de minutos.
     */
    public function lockAccount(int $id, int $minutes = 15): bool;

    /**
     * Alternar el estado activo/inactivo de la cuenta.
     */
    public function toggleStatus(int $id): bool;

    /**
     * Actualizar la contraseña del usuario (hash ya aplicado).
     */
    public function updatePassword(int $userId, string $newPassword): bool;

    /**
     * Marcar el email del usuario como verificado.
     */
    public function verifyEmail(int $id): bool;

    /**
     * Actualizar la URL del avatar del usuario.
     */
    public function updateAvatar(int $id, string $avatarUrl): bool;

    /**
     * Obtener todos los usuarios que tienen un rol concreto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByRole(string $roleSlug): array;

    /**
     * Actualizar las preferencias JSON del usuario.
     *
     * @param array<string, mixed> $preferences
     */
    public function updatePreferences(int $id, array $preferences): bool;

    /**
     * Anonimizar los datos personales de un usuario (GDPR).
     */
    public function anonymize(int $id): bool;

    /**
     * Obtener la lista de usuarios activos (id, name, email).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveUsersList(): array;

    /**
     * Obtener el staff asignado a un café con sus roles concatenados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStaffByCafe(int $cafeId): array;

    /**
     * Obtener los datos completos de un miembro del staff en un café.
     *
     * @return array<string, mixed>|null
     */
    public function getStaffById(int $userId, int $cafeId): ?array;

    /**
     * Verificar si un usuario pertenece al staff de un café.
     */
    public function existsInCafe(int $userId, int $cafeId): bool;

    /**
     * Obtener datos básicos (id, name) de un miembro del staff en un café.
     *
     * @return array<string, mixed>|null
     */
    public function getStaffBasicById(int $userId, int $cafeId): ?array;

    /**
     * Verificar la contraseña del usuario (con rehash automático si es necesario).
     *
     * @param array<string, mixed> $user
     */
    public function verifyPassword(array $user, string $password): bool;

    /**
     * Registrar intento de login fallido. Bloquea la cuenta si se supera el límite.
     */
    public function registerFailedAttempt(int $id): void;

    /**
     * Resetear intentos de login tras login exitoso.
     */
    public function clearLoginAttempts(int $id): void;

    /**
     * Comprobar si una cuenta está bloqueada temporalmente.
     *
     * @param array<string, mixed> $user
     */
    public function isLocked(array $user): bool;

    /**
     * Obtener los minutos restantes de bloqueo.
     *
     * @param array<string, mixed> $user
     */
    public function lockoutMinutesRemaining(array $user): int;
}
