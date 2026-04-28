<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Result;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthTokenServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\EmailVerificationServiceInterface;
use Override;
use Random\RandomException;

/**
 * Servicio de verificación de email.
 *
 * Gestiona el envío y validación de tokens de verificación de email.
 */
final class EmailVerificationService implements EmailVerificationServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly AuthTokenServiceInterface $tokenService,
        private readonly EmailServiceInterface $emailService,
    ) {}

    /**
     * Enviar email de verificación al usuario.
     *
     * @return Result
     * @throws RandomException
     */
    #[Override]
    public function sendVerificationEmail(int $userId): Result
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return Result::fail('Usuario no encontrado.');
        }

        if ($this->tokenService->isEmailVerified($userId)) {
            return Result::fail('Email ya verificado.');
        }

        try {
            $token = $this->tokenService->createEmailVerificationToken($userId);
            $verifyUrl = Env::get('APP_URL') . "/auth/verify-email?token=$token";

            $this->emailService->sendVerificationEmail(
                $user->email,
                $user->name,
                $verifyUrl
            );

            return Result::ok('Email de verificación enviado correctamente');
        } catch (RandomException) {
            return Result::fail('Error generando token.');
        }
    }

    /**
     * Verificar email con token.
     *
     * @return Result<mixed>
     */
    #[Override]
    public function verifyEmailToken(string $token): Result
    {
        return $this->tokenService->verifyEmail($token);
    }
}
