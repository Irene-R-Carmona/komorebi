<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface UserManagementServiceInterface
{
    public function getUsersWithRoles(): array;

    public function validateUserData(array $data, bool $isUpdate = false): Result;

    public function createUser(array $data): Result;

    public function updateUser(int $userId, array $data): Result;

    public function deactivateUser(int $userId): Result;

    public function toggleUserStatus(int $userId): Result;
}
