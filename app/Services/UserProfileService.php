<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Result;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use RuntimeException;

/**
 * Servicio de perfil de usuario.
 *
 * Gestiona la lectura y actualización del perfil, avatar y permisos.
 */
final class UserProfileService implements UserProfileServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly User $userModel,
    ) {}

    /**
     * Obtiene el perfil del usuario autenticado en la sesión actual.
     *
     * @return array<string, mixed>
     * @throws AuthenticationException
     * @throws NotFoundException
     */
    #[\Override]
    public function getCurrentProfile(): array
    {
        $userId = Session::userId();

        if ($userId === null) {
            throw AuthenticationException::notAuthenticated();
        }

        return $this->getProfile($userId);
    }

    /**
     * Obtiene el perfil de un usuario específico.
     *
     * @return array<string, mixed>
     * @throws NotFoundException
     */
    #[\Override]
    public function getProfile(int $userId): array
    {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw NotFoundException::user($userId);
        }

        $roles = $this->userRepo->getRoles($userId);
        $roleCodes = \array_column($roles, 'slug');

        return [
            'id'          => (int) ($user['id'] ?? 0),
            'uuid'        => $user['uuid'] ?? null,
            'name'        => $user['name'] ?? '',
            'email'       => $user['email'] ?? null,
            'roles'       => $roleCodes,
            'is_active'   => isset($user['is_active']) ? (bool) $user['is_active'] : false,
            'cafe_id'     => isset($user['cafe_id']) && $user['cafe_id'] ? (int) $user['cafe_id'] : null,
            'avatar'      => $user['avatar'] ?? null,
            'preferences' => isset($user['preferences']) && $user['preferences']
                ? \json_decode($user['preferences'], true)
                : [],
            'created_at'  => $user['created_at'] ?? null,
        ];
    }

    /**
     * Actualiza el perfil del usuario.
     *
     * @param string|array<string, mixed> $nameOrData
     */
    #[\Override]
    public function updateProfile(int $userId, string|array $nameOrData, ?string $email = null): Result
    {
        $updatePayload = [];

        if (\is_array($nameOrData)) {
            $data     = $nameOrData;
            $name     = isset($data['name']) ? \trim((string) $data['name']) : null;
            $emailVal = isset($data['email']) ? \strtolower(\trim((string) $data['email'])) : null;
        } else {
            $name     = \trim($nameOrData);
            $emailVal = $email !== null ? \strtolower(\trim($email)) : null;
        }

        if ($name !== null) {
            if ($name === '' || \mb_strlen($name) > 100) {
                return Result::fail('Nombre inválido (1-100 caracteres).');
            }
            $updatePayload['name'] = $name;
        }

        if ($emailVal !== null) {
            if (!\filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
                return Result::fail('Email inválido.');
            }
            $updatePayload['email'] = $emailVal;
        }

        if (empty($updatePayload)) {
            return Result::fail('No hay campos para actualizar.');
        }

        try {
            $this->userRepo->update($userId, $updatePayload);

            if (Session::userId() === $userId) {
                if (isset($updatePayload['name'])) {
                    Session::set('user_name', $updatePayload['name']);
                }
                if (isset($updatePayload['email'])) {
                    Session::set('user_email', $updatePayload['email']);
                }
            }

            return Result::ok('Perfil actualizado correctamente');
        } catch (RuntimeException $e) {
            return Result::fail($e->getMessage());
        }
    }

    /**
     * Actualiza el avatar del usuario.
     */
    #[\Override]
    public function updateAvatar(int $userId, ?string $filename): Result
    {
        try {
            $this->userModel->updateAvatar($userId, $filename);

            return Result::ok($filename);
        } catch (RuntimeException $e) {
            return Result::fail($e->getMessage());
        }
    }

    /**
     * Obtiene usuarios por rol.
     *
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function getUsersByRole(string $role): array
    {
        return \method_exists($this->userRepo, 'findByRole')
            ? $this->userRepo->findByRole($role)
            : [];
    }

    /**
     * Comprueba si un usuario tiene un permiso.
     */
    #[\Override]
    public function hasPermission(int $userId, string $permission): bool
    {
        return \method_exists($this->userRepo, 'hasPermission')
            ? (bool) $this->userRepo->hasPermission($userId, $permission)
            : false;
    }
}
