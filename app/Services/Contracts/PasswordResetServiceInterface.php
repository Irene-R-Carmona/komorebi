<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Contrato para el servicio de restablecimiento de contraseña.
 */
interface PasswordResetServiceInterface
{
    /**
     * Solicitar reset de contraseña (forgot password).
     *
     * @return Result
     */
    public function requestPasswordReset(string $email, string $ipAddress, ?string $userAgent = null): Result;

    /**
     * Validar token de reset sin consumirlo.
     *
     * @return Result<array<string,mixed>>
     */
    public function validatePasswordResetToken(string $token): Result;

    /**
     * Cambiar contraseña con token.
     *
     * @return Result
     */
    public function resetPasswordWithToken(string $token, string $newPassword, string $confirmPassword): Result;
}
