<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Container;
use App\Core\Database;
use App\Core\Result;
use App\Repositories\Contracts\UserManagementRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserManagementServiceInterface;
use Exception;
use Override;

final class UserManagementService extends BaseService implements UserManagementServiceInterface
{
    private UserRepositoryInterface $userRepo;
    private UserManagementRepositoryInterface $userMgmtRepo;

    public function __construct(
        ?UserRepositoryInterface $userRepo = null,
        ?UserManagementRepositoryInterface $userMgmtRepo = null,
    ) {
        $this->userRepo = $userRepo ?? Container::make(UserRepositoryInterface::class);
        $this->userMgmtRepo = $userMgmtRepo ?? Container::make(UserManagementRepositoryInterface::class);
    }

    #[Override]
    public function getUsersWithRoles(): array
    {
        try {
            return $this->userMgmtRepo->getUsersWithRoles();
        } catch (Exception $e) {
            $this->logError('[UserManagementService] getUsersWithRoles', ['error' => $e->getMessage()]);

            return [];
        }
    }

    #[Override]
    public function validateUserData(array $data, bool $isUpdate = false): Result
    {
        $errors = [];

        if (empty($data['name']) || \strlen($data['name']) < 2 || \strlen($data['name']) > 100) {
            $errors['name'] = 'El nombre debe tener entre 2 y 100 caracteres';
        }

        if (empty($data['email']) || !\filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido';
        }

        if ((!$isUpdate || !empty($data['password'])) && (empty($data['password']) || \strlen($data['password']) < 8)) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
        }

        if (empty($data['role_id']) || !\is_numeric($data['role_id'])) {
            $errors['role_id'] = 'Debe seleccionar un rol válido';
        }

        if (!empty($errors)) {
            return Result::fail('Datos de usuario inválidos', 'validation', ['errors' => $errors]);
        }

        return Result::ok();
    }

    #[Override]
    public function createUser(array $data): Result
    {
        $guard = $this->guardCreate($data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            return Database::transaction(function () use ($data): Result {
                $userId = $this->userRepo->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => \password_hash($data['password'], PASSWORD_ARGON2ID),
                    'is_active' => 1,
                    'created_at' => \date('Y-m-d H:i:s'),
                ]);
                $this->userRepo->assignRole($userId, (int) $data['role_id']);

                return Result::ok(['id' => $userId, 'message' => 'Usuario creado exitosamente']);
            });
        } catch (Exception $e) {
            return Result::fail('Error al crear usuario: ' . $e->getMessage());
        }
    }

    #[Override]
    public function updateUser(int $userId, array $data): Result
    {
        $guard = $this->guardUpdate($userId, $data);
        if ($guard !== null) {
            return $guard;
        }

        try {
            return Database::transaction(function () use ($userId, $data): Result {
                $fields = ['name' => $data['name'], 'email' => $data['email']];
                if (!empty($data['password'])) {
                    $fields['password'] = \password_hash($data['password'], PASSWORD_ARGON2ID);
                }
                $this->userRepo->update($userId, $fields);

                if (!empty($data['role_id'])) {
                    $this->userMgmtRepo->clearRoles($userId);
                    $this->userRepo->assignRole($userId, (int) $data['role_id']);
                }

                return Result::ok(['ok' => true, 'message' => 'Usuario actualizado exitosamente']);
            });
        } catch (Exception $e) {
            return Result::fail('Error al actualizar usuario: ' . $e->getMessage());
        }
    }

    private function guardCreate(array $data): ?Result
    {
        $v = $this->validateUserData($data, false);
        if ($v->error !== null) {
            return $v;
        }

        return $this->userRepo->findByEmail($data['email'])
            ? Result::fail('El email ya está registrado')
            : null;
    }

    private function guardUpdate(int $userId, array $data): ?Result
    {
        $v = $this->validateUserData($data, true);
        if ($v->error !== null) {
            return $v;
        }

        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return Result::fail('Usuario no encontrado');
        }

        $existing = $data['email'] !== $user['email'] ? $this->userRepo->findByEmail($data['email']) : null;
        $emailTaken = $existing && (int) $existing['id'] !== $userId;

        return $emailTaken ? Result::fail('El email ya está registrado') : null;
    }

    #[Override]
    public function deactivateUser(int $userId): Result
    {
        try {
            $this->userRepo->setActive($userId, false);

            return Result::ok(['ok' => true, 'message' => 'Usuario desactivado exitosamente']);
        } catch (Exception $e) {
            return Result::fail('Error al desactivar usuario: ' . $e->getMessage());
        }
    }

    #[Override]
    public function toggleUserStatus(int $userId): Result
    {
        try {
            if (!$this->userRepo->findById($userId)) {
                return Result::fail('Usuario no encontrado');
            }

            $this->userRepo->toggleStatus($userId);
            $updated = $this->userRepo->findById($userId);
            $newStatus = (bool) ($updated['is_active'] ?? false);
            $statusText = $newStatus ? 'activado' : 'desactivado';

            return Result::ok([
                'is_active' => $newStatus,
                'message' => "Usuario $statusText exitosamente",
            ]);
        } catch (Exception $e) {
            return Result::fail('Error al cambiar estado: ' . $e->getMessage());
        }
    }
}
