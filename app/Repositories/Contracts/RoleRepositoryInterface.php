<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\RoleDTO;

interface RoleRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function findAllWithCounts(): array;

    /** @return array<int, array<string, mixed>> Cada rol incluye 'permissions': array de {id, name} */
    public function getAllWithPermissions(): array;

    /** @return array<string, int> */
    public function getStats(): array;

    public function findById(int $id): ?RoleDTO;

    public function findByCode(string $code): ?RoleDTO;

    public function create(string $code, string $name, ?string $description = null): int;

    public function update(int $id, ?string $name = null, ?string $description = null): bool;

    public function delete(int $id): bool;

    public function countUsers(int $roleId): int;

    public function grantPermission(int $roleId, int $permissionId): bool;

    public function revokePermission(int $roleId, int $permissionId): bool;

    /** @return array<int, array<string, mixed>> */
    public function findAllPermissions(): array;

    /** @return array{id: int, code: string, name: string, description: ?string, resource: ?string, action: ?string}|null */
    public function findPermissionById(int $id): ?array;
}
