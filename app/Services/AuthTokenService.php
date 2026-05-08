<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Container;
use App\Core\Result;
use App\Repositories\Contracts\AuthTokenRepositoryInterface;
use App\Services\Contracts\AuthTokenServiceInterface;
use Override;
use Random\RandomException;

/**
 * Servicio de Tokens de Autenticación
 */
final class AuthTokenService implements AuthTokenServiceInterface
{
    private AuthTokenRepositoryInterface $authTokenRepo;
    private int $emailTokenTtl = Cache::TTL_HOUR;
    private int $passwordTokenTtl = Cache::TTL_HOUR;

    public function __construct(?AuthTokenRepositoryInterface $authTokenRepo = null)
    {
        $this->authTokenRepo = $authTokenRepo ?? Container::make(AuthTokenRepositoryInterface::class);
    }

    /** @throws RandomException */
    private function generateToken(): string
    {
        return \bin2hex(\random_bytes(32));
    }

    private function hashToken(string $token): string
    {
        return \hash('sha256', $token);
    }

    /** @throws RandomException */
    #[Override]
    public function createEmailVerificationToken(int $userId): string
    {
        $this->authTokenRepo->deletePendingEmailVerificationTokensByUser($userId);

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = \date('Y-m-d H:i:s', \time() + $this->emailTokenTtl);

        $this->authTokenRepo->createEmailVerificationToken($userId, $tokenHash, $expiresAt);

        return $token;
    }

    /**
     * Verifica un token de email
     *
     * @param string $token
     * @return Result Data contiene ['user_id' => int] si exitoso
     */
    #[Override]
    public function verifyEmail(string $token): Result
    {
        $tokenHash = $this->hashToken($token);

        $row = $this->authTokenRepo->findValidEmailVerificationToken($tokenHash);

        if ($row === null) {
            return Result::fail('Token inválido o expirado.');
        }

        $this->authTokenRepo->markEmailVerificationTokenVerified($row['id']);
        $this->authTokenRepo->markUserEmailVerified($row['user_id']);

        return Result::ok(['user_id' => $row['user_id']]);
    }

    #[Override]
    public function isEmailVerified(int $userId): bool
    {
        return $this->authTokenRepo->isUserEmailVerified($userId);
    }

    /** @throws RandomException */
    #[Override]
    public function createPasswordResetToken(int $userId, string $ipAddress, ?string $userAgent = null): string
    {
        $this->authTokenRepo->deleteExpiredPasswordResetTokensByUser($userId);

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = \date('Y-m-d H:i:s', \time() + $this->passwordTokenTtl);

        $this->authTokenRepo->createPasswordResetToken($userId, $tokenHash, $expiresAt, $ipAddress, $userAgent);

        return $token;
    }

    /**
     * Valida un token de reset de contraseña (sin consumirlo)
     *
     * @param string $token
     * @return Result Data contiene ['user_id' => int] si válido
     */
    #[Override]
    public function validatePasswordResetToken(string $token): Result
    {
        $tokenHash = $this->hashToken($token);

        $row = $this->authTokenRepo->findValidPasswordResetToken($tokenHash);

        if ($row === null) {
            return Result::fail('Token inválido, expirado o ya utilizado.');
        }

        return Result::ok(['user_id' => $row['user_id']]);
    }

    #[Override]
    public function consumePasswordResetToken(string $token): bool
    {
        $tokenHash = $this->hashToken($token);

        return $this->authTokenRepo->markPasswordResetTokenUsed($tokenHash);
    }

    #[Override]
    public function cleanupExpiredTokens(): int
    {
        return $this->authTokenRepo->deleteExpiredEmailVerificationTokens()
            + $this->authTokenRepo->deleteExpiredPasswordResetTokens();
    }
}
