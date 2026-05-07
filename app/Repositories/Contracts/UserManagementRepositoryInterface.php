<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UserManagementRepositoryInterface
{
    /**
     * Todos los usuarios con roles concatenados para la tabla de gestión admin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUsersWithRoles(): array;

    /**
     * Eliminar todos los roles de un usuario (previo a reasignación).
     */
    public function clearRoles(int $userId): bool;
}
