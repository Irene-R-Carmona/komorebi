<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use DateTimeImmutable;

/**
 * Contrato para ApiTokenRepository.
 *
 * Define las operaciones de persistencia de tokens Bearer opacos.
 * El token en texto plano nunca se almacena — solo su hash SHA-256.
 */
interface ApiTokenRepositoryInterface
{
    /**
     * Busca un token activo (no revocado, no expirado) por su hash SHA-256.
     *
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hash): ?array;

    /**
     * Busca un token por ID verificando el propietario (para ownership check).
     *
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $id, int $userId): ?array;

    /**
     * Persiste un nuevo token y retorna el ID insertado.
     */
    public function createToken(
        int $userId,
        string $name,
        string $tokenHash,
        ?DateTimeImmutable $expiresAt = null
    ): int;

    /**
     * Revoca un token (solo si pertenece al usuario indicado).
     * Retorna true si se revocó, false si no existía o ya estaba revocado.
     */
    public function revoke(int $tokenId, int $userId): bool;

    /**
     * Lista los tokens activos de un usuario sin exponer el hash.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array;

    /**
     * Actualiza last_used_at del token indicado al momento actual.
     */
    public function updateLastUsed(int $tokenId): void;
}
