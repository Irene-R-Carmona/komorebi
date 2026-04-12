<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Contrato para el servicio de verificación de email.
 */
interface EmailVerificationServiceInterface
{
    /**
     * Enviar email de verificación al usuario.
     *
     * @return Result
     */
    public function sendVerificationEmail(int $userId): Result;

    /**
     * Verificar email con token.
     *
     * @return Result<mixed>
     */
    public function verifyEmailToken(string $token): Result;
}
