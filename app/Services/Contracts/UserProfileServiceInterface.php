<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Contrato para el servicio de perfil de usuario.
 */
interface UserProfileServiceInterface
{
    /**
     * Obtiene el perfil del usuario autenticado en la sesión actual.
     *
     * @return array<string, mixed>
     */
    public function getCurrentProfile(): array;

    /**
     * Obtiene el perfil de un usuario específico.
     *
     * @return array<string, mixed>
     */
    public function getProfile(int $userId): array;

    /**
     * Actualiza el perfil del usuario.
     *
     * @param string|array<string, mixed> $nameOrData
     */
    public function updateProfile(int $userId, string|array $nameOrData, ?string $email = null): Result;

    /**
     * Actualiza el avatar del usuario.
     */
    public function updateAvatar(int $userId, ?string $filename): Result;

    /**
     * Obtiene usuarios por rol.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUsersByRole(string $role): array;

    /**
     * Comprueba si un usuario tiene un permiso.
     */
    public function hasPermission(int $userId, string $permission): bool;
}
