<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\AuthTokenRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio para tokens de autenticación.
 *
 * Gestiona la persistencia de tokens de verificación de email
 * y tokens de restablecimiento de contraseña.
 */
final class AuthTokenRepository implements AuthTokenRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    // -------------------------------------------------------------------------
    // Email verification tokens
    // -------------------------------------------------------------------------

    #[Override]
    public function deletePendingEmailVerificationTokensByUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM email_verification_tokens WHERE user_id = :id AND verified_at IS NULL'
        );
        $stmt->execute(['id' => $userId]);
    }

    #[Override]
    public function createEmailVerificationToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :hash, :expires)'
        );
        $stmt->execute(['user_id' => $userId, 'hash' => $tokenHash, 'expires' => $expiresAt]);
    }

    /**
     * @return array{id: int, user_id: int}|null
     */
    #[Override]
    public function findValidEmailVerificationToken(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id FROM email_verification_tokens
             WHERE token_hash = :hash AND expires_at > NOW() AND verified_at IS NULL'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        return ['id' => (int) $result['id'], 'user_id' => (int) $result['user_id']];
    }

    #[Override]
    public function markEmailVerificationTokenVerified(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE email_verification_tokens SET verified_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    #[Override]
    public function markUserEmailVerified(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    #[Override]
    public function isUserEmailVerified(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT email_verified_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false && !empty($result['email_verified_at']);
    }

    #[Override]
    public function deleteExpiredEmailVerificationTokens(): int
    {
        $stmt = $this->db->query('DELETE FROM email_verification_tokens WHERE expires_at < NOW()');

        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // Password reset tokens
    // -------------------------------------------------------------------------

    #[Override]
    public function deleteExpiredPasswordResetTokensByUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM password_reset_tokens WHERE user_id = :id AND used_at IS NULL AND expires_at < NOW()'
        );
        $stmt->execute(['id' => $userId]);
    }

    #[Override]
    public function createPasswordResetToken(
        int $userId,
        string $tokenHash,
        string $expiresAt,
        string $ipAddress,
        ?string $userAgent,
    ): void {
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
    }

    /**
     * @return array{user_id: int}|null
     */
    #[Override]
    public function findValidPasswordResetToken(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id FROM password_reset_tokens
             WHERE token_hash = :hash AND expires_at > NOW() AND used_at IS NULL'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        return ['user_id' => (int) $result['user_id']];
    }

    #[Override]
    public function markPasswordResetTokenUsed(string $tokenHash): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = :hash AND used_at IS NULL'
        );

        return $stmt->execute(['hash' => $tokenHash]);
    }

    #[Override]
    public function deleteExpiredPasswordResetTokens(): int
    {
        $stmt = $this->db->query(
            'DELETE FROM password_reset_tokens WHERE expires_at < NOW() AND used_at IS NULL'
        );

        return $stmt->rowCount();
    }
}
