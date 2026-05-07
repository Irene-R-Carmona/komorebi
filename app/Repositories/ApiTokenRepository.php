<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\ApiTokenRepositoryInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Repositorio para la tabla api_tokens.
 *
 * Almacena solo el hash SHA-256 del token opaco.
 * El token en texto plano NUNCA se persiste aquí.
 */
final class ApiTokenRepository extends AbstractRepository implements ApiTokenRepositoryInterface
{
    #[Override]
    protected function getTable(): string
    {
        return 'api_tokens';
    }

    /** @return array<string> */
    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'user_id', 'name', 'token_hash', 'last_used_at', 'expires_at', 'revoked_at', 'created_at'];
    }

    /**
     * Busca un token activo (no revocado, no expirado) por su hash.
     *
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hash): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, user_id, name, last_used_at, expires_at, revoked_at, created_at
             FROM api_tokens
             WHERE token_hash = ?
               AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Busca un token por ID y propietario (para revocación con ownership check).
     *
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, user_id, name, revoked_at, created_at
             FROM api_tokens
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createToken(int $userId, string $name, string $tokenHash, ?DateTimeImmutable $expiresAt = null): int
    {
        $stmt = $this->getDb()->prepare(
            'INSERT INTO api_tokens (user_id, name, token_hash, expires_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $name,
            $tokenHash,
            $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    /**
     * Revoca un token (solo si pertenece al usuario indicado).
     */
    public function revoke(int $tokenId, int $userId): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE api_tokens
             SET revoked_at = NOW()
             WHERE id = ? AND user_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$tokenId, $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Lista los tokens no revocados de un usuario, sin exponer el hash.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, name, last_used_at, expires_at, created_at
             FROM api_tokens
             WHERE user_id = ? AND revoked_at IS NULL
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza la marca de último uso (best-effort, sin excepciones).
     */
    public function updateLastUsed(int $tokenId): void
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$tokenId]);
    }
}
