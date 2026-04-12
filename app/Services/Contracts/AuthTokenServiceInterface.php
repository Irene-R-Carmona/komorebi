<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AuthTokenServiceInterface
{
    public function createEmailVerificationToken(int $userId): string;

    public function verifyEmail(string $token): Result;

    public function isEmailVerified(int $userId): bool;

    public function createPasswordResetToken(int $userId, string $ipAddress, ?string $userAgent = null): string;

    public function validatePasswordResetToken(string $token): Result;

    public function consumePasswordResetToken(string $token): bool;

    public function cleanupExpiredTokens(): int;
}
