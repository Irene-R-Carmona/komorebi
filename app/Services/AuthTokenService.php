<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Result;
use App\Services\Contracts\AuthTokenServiceInterface;
use PDO;
use Random\RandomException;

/**
 * Servicio de Tokens de Autenticación
 */
final class AuthTokenService implements AuthTokenServiceInterface
{
    private PDO $db;
    private int $emailTokenTtl = 3600;
    private int $passwordTokenTtl = 3600;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
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
    #[\Override]
    public function createEmailVerificationToken(int $userId): string
    {
        $stmt = $this->db->prepare(
            'DELETE FROM email_verification_tokens WHERE user_id = :id AND verified_at IS NULL'
        );
        $stmt->execute(['id' => $userId]);

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = \date('Y-m-d H:i:s', \time() + $this->emailTokenTtl);

        $stmt = $this->db->prepare(
            'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :hash, :expires)'
        );
        $stmt->execute(['user_id' => $userId, 'hash' => $tokenHash, 'expires' => $expiresAt]);

        return $token;
    }

    /**
     * Verifica un token de email
     *
     * @param string $token
     * @return Result Data contiene ['user_id' => int] si exitoso
     */
    #[\Override]
    public function verifyEmail(string $token): Result
    {
        $tokenHash = $this->hashToken($token);

        $stmt = $this->db->prepare(
            'SELECT id, user_id FROM email_verification_tokens
             WHERE token_hash = :hash AND expires_at > NOW() AND verified_at IS NULL'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $result = $stmt->fetch();

        if (!$result) {
            return Result::fail('Token inválido o expirado.');
        }

        $userId = (int) $result['user_id'];

        $stmt = $this->db->prepare('UPDATE email_verification_tokens SET verified_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => (int) $result['id']]);

        $stmt = $this->db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        return Result::ok(['user_id' => $userId]);
    }

    #[\Override]
    public function isEmailVerified(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT email_verified_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $result = $stmt->fetch();

        return $result && !empty($result['email_verified_at']);
    }

    /** @throws RandomException */
    #[\Override]
    public function createPasswordResetToken(int $userId, string $ipAddress, ?string $userAgent = null): string
    {
        $stmt = $this->db->prepare(
            'DELETE FROM password_reset_tokens WHERE user_id = :id AND used_at IS NULL AND expires_at < NOW()'
        );
        $stmt->execute(['id' => $userId]);

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = \date('Y-m-d H:i:s', \time() + $this->passwordTokenTtl);

        $stmt = $this->db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address, user_agent)
             VALUES (:user_id, :hash, :expires, :ip, :ua)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'hash' => $tokenHash,
            'expires' => $expiresAt,
            'ip' => $ipAddress,
            'ua' => $userAgent,
        ]);

        return $token;
    }

    /**
     * Valida un token de reset de contraseña (sin consumirlo)
     *
     * @param string $token
     * @return Result Data contiene ['user_id' => int] si válido
     */
    #[\Override]
    public function validatePasswordResetToken(string $token): Result
    {
        $tokenHash = $this->hashToken($token);

        $stmt = $this->db->prepare(
            'SELECT user_id FROM password_reset_tokens
             WHERE token_hash = :hash AND expires_at > NOW() AND used_at IS NULL'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $result = $stmt->fetch();

        if (!$result) {
            return Result::fail('Token inválido, expirado o ya utilizado.');
        }

        return Result::ok(['user_id' => (int) $result['user_id']]);
    }

    #[\Override]
    public function consumePasswordResetToken(string $token): bool
    {
        $tokenHash = $this->hashToken($token);
        $stmt = $this->db->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = :hash AND used_at IS NULL'
        );

        return $stmt->execute(['hash' => $tokenHash]);
    }

    #[\Override]
    public function cleanupExpiredTokens(): int
    {
        $stmt = $this->db->query('DELETE FROM email_verification_tokens WHERE expires_at < NOW()');
        $deletedEmail = $stmt->rowCount();

        $stmt = $this->db->query('DELETE FROM password_reset_tokens WHERE expires_at < NOW() AND used_at IS NULL');
        $deletedPassword = $stmt->rowCount();

        return $deletedEmail + $deletedPassword;
    }
}
