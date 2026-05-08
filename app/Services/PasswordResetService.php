<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Result;
use App\Domain\Validation\UserConstraints;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthTokenServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\PasswordResetServiceInterface;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use Override;
use Random\RandomException;
use RuntimeException;

/**
 * Servicio de restablecimiento de contraseña.
 *
 * Gestiona el flujo completo de recuperación de contraseña:
 * solicitud de token, validación y cambio con token.
 */
final class PasswordResetService implements PasswordResetServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly AuthTokenServiceInterface $tokenService,
        private readonly SessionManagementServiceInterface $sessionService,
        private readonly RateLimitingServiceInterface $rateLimiter,
        private readonly EmailServiceInterface $emailService,
    ) {
    }

    /**
     * Solicitar reset de contraseña (forgot password).
     *
     * @return Result
     * @throws RandomException
     */
    #[Override]
    public function requestPasswordReset(string $email, string $ipAddress, ?string $userAgent = null): Result
    {
        $email = \strtolower(\trim($email));

        // Rate limiting
        $blocked = $this->rateLimiter->isBlocked('password_reset', $email);
        if (!empty($blocked['blocked'])) {
            $minutes = isset($blocked['minutes_remaining']) ? (int) $blocked['minutes_remaining'] : 0;

            return Result::fail("Demasiados intentos. Intenta en {$minutes} minutos.");
        }

        $user = $this->userRepo->findByEmail($email);

        // Mensaje genérico por seguridad (no revelar si email existe)
        if (!$user) {
            $this->rateLimiter->recordAttempt('password_reset', $email, $ipAddress);

            return Result::ok('Si el email existe, recibirás instrucciones para recuperar tu contraseña');
        }

        try {
            $token = $this->tokenService->createPasswordResetToken((int) $user['id'], $ipAddress, $userAgent);
            $resetUrl = Env::get('APP_URL') . "/auth/reset-password?token=$token";

            $this->emailService->sendPasswordResetEmail(
                (string) ($user['email'] ?? ''),
                (string) ($user['name'] ?? ''),
                $resetUrl
            );

            $this->rateLimiter->clearAttempts('password_reset', $email);

            $this->sessionService->logAuthEvent(
                (int) $user['id'],
                'password_reset',
                $ipAddress,
                $userAgent,
                null,
                true,
                'Reset solicitado'
            );

            return Result::ok('Si el email existe, recibirás instrucciones para recuperar tu contraseña');
        } catch (RandomException) {
            $this->rateLimiter->recordAttempt('password_reset', $email, $ipAddress);

            return Result::fail('Error generando token.');
        }
    }

    /**
     * Validar token de reset (sin consumirlo).
     *
     * @return Result<array<string,mixed>>
     */
    #[Override]
    public function validatePasswordResetToken(string $token): Result
    {
        return $this->tokenService->validatePasswordResetToken($token);
    }

    /**
     * Cambiar contraseña con token.
     *
     * @return Result
     */
    #[Override]
    public function resetPasswordWithToken(string $token, string $newPassword, string $confirmPassword): Result
    {
        // Validar token
        $validation = $this->tokenService->validatePasswordResetToken($token);
        if ($validation->error !== null) {
            return $validation;
        }

        if (\mb_strlen($newPassword) < UserConstraints::PASSWORD_MIN_LENGTH) {
            return Result::fail('La contraseña debe tener al menos 8 caracteres.');
        }

        if ($newPassword !== $confirmPassword) {
            return Result::fail('Las contraseñas no coinciden.');
        }

        $userId = (int) $validation->data['user_id'];

        try {
            $this->userRepo->updatePassword($userId, $newPassword);
            $this->tokenService->consumePasswordResetToken($token);
            $this->sessionService->revokeAllOtherSessions($userId, '', $userId);
            $this->sessionService->logAuthEvent($userId, 'password_reset', '', null, null, true, 'Reset completado');

            return Result::ok('Contraseña actualizada correctamente');
        } catch (RuntimeException $e) {
            return Result::fail($e->getMessage());
        }
    }
}
