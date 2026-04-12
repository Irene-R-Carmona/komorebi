<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Contrato para operaciones de gestión de cuenta de usuario.
 *
 * Cubre cambio de contraseña, baja de cuenta y ciclo de vida de activación.
 */
interface UserAccountServiceInterface
{
    /**
     * Cambia la contraseña del usuario.
     *
     * @return Result
     */
    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        ?string $confirmPassword = null,
    ): Result;

    /**
     * Elimina (anonimiza) la cuenta del usuario previa verificación de contraseña.
     *
     * @return Result
     */
    public function deleteAccount(int $userId, string $password): Result;

    /**
     * Marca el email del usuario como verificado.
     *
     * @return Result
     */
    public function verifyEmail(int $userId): Result;

    /**
     * Desactiva la cuenta de un usuario.
     *
     * @return Result
     */
    public function deactivateAccount(int $userId): Result;

    /**
     * Reactiva la cuenta de un usuario.
     *
     * @return Result
     */
    public function reactivateAccount(int $userId): Result;
}
