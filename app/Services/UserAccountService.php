<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Core\Session;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserAccountServiceInterface;
use RuntimeException;

/**
 * Servicio de gestión de cuenta de usuario.
 *
 * Gestiona cambio de contraseña, eliminación de cuenta y ciclo de vida de activación.
 */
final class UserAccountService implements UserAccountServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly User $userModel,
    ) {
    }

    /**
     * Cambia la contraseña del usuario.
     */
    #[\Override]
    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        ?string $confirmPassword = null,
    ): Result {
        if ($confirmPassword !== null && $newPassword !== $confirmPassword) {
            return Result::fail('Las contraseñas no coinciden.');
        }

        if (\mb_strlen($newPassword) < 8) {
            return Result::fail('La contraseña debe tener al menos 8 caracteres.');
        }

        // Preferir el modelo legacy; fallback al repo si no devuelve usuario
        $user = $this->userModel->findById($userId) ?? $this->userRepo->findById($userId);

        if (!$user) {
            return Result::fail('Usuario no encontrado.');
        }

        $storedHash = $user['password_hash'] ?? $user['password'] ?? null;

        if (!$storedHash || !\password_verify($currentPassword, $storedHash)) {
            return Result::fail('La contraseña actual es incorrecta.');
        }

        try {
            $this->userModel->updatePassword($userId, $newPassword);

            return Result::ok('Contraseña actualizada correctamente');
        } catch (RuntimeException $e) {
            Logger::error('[UserAccountService] Error al cambiar contraseña', ['exception' => $e->getMessage()]);

            return Result::fail('No se pudo cambiar la contraseña.');
        }
    }

    /**
     * Elimina (anonimiza) la cuenta del usuario previa verificación de contraseña.
     */
    #[\Override]
    public function deleteAccount(int $userId, string $password): Result
    {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            return Result::fail('Usuario no encontrado.');
        }

        if (!$this->userModel->verifyPassword($user, $password)) {
            return Result::fail('Contraseña incorrecta.');
        }

        try {
            $this->userRepo->anonymize($userId);

            if (Session::userId() === $userId) {
                Session::destroy();
            }

            return Result::ok('Cuenta eliminada correctamente');
        } catch (RuntimeException $e) {
            Logger::error('[UserAccountService] Error al eliminar cuenta', ['exception' => $e->getMessage()]);

            return Result::fail('No se pudo eliminar la cuenta.');
        }
    }

    /**
     * Marca el email del usuario como verificado.
     */
    #[\Override]
    public function verifyEmail(int $userId): Result
    {
        try {
            $this->userModel->verifyEmail($userId);

            return Result::ok('Email verificado');
        } catch (RuntimeException $e) {
            Logger::error('[UserAccountService] Error al verificar email', ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage());
        }
    }

    /**
     * Desactiva la cuenta de un usuario.
     */
    #[\Override]
    public function deactivateAccount(int $userId): Result
    {
        try {
            $ok = $this->userModel->setActive($userId, false);

            return $ok
                ? Result::ok('Cuenta desactivada')
                : Result::fail('No se pudo desactivar la cuenta');
        } catch (RuntimeException $e) {
            Logger::error('[UserAccountService] Error al desactivar cuenta', ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage());
        }
    }

    /**
     * Reactiva la cuenta de un usuario.
     */
    #[\Override]
    public function reactivateAccount(int $userId): Result
    {
        try {
            $ok = $this->userModel->setActive($userId, true);

            return $ok
                ? Result::ok('Cuenta reactivada')
                : Result::fail('No se pudo reactivar la cuenta');
        } catch (RuntimeException $e) {
            Logger::error('[UserAccountService] Error al reactivar cuenta', ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage());
        }
    }
}
