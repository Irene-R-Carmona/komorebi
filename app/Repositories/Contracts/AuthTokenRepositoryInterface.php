<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Contrato para AuthTokenRepository.
 *
 * Define las operaciones de persistencia de tokens de verificación de email
 * y tokens de restablecimiento de contraseña.
 * Los tokens en texto plano nunca se almacenan — solo su hash SHA-256.
 */
interface AuthTokenRepositoryInterface
{
    // -------------------------------------------------------------------------
    // Email verification tokens
    // -------------------------------------------------------------------------

    /**
     * Elimina todos los tokens de verificación de email pendientes (no verificados)
     * de un usuario. Se llama antes de crear un nuevo token para invalidar anteriores.
     */
    public function deletePendingEmailVerificationTokensByUser(int $userId): void;

    /**
     * Inserta un nuevo token de verificación de email.
     *
     * @param string $expiresAt Fecha de expiración en formato 'Y-m-d H:i:s'
     */
    public function createEmailVerificationToken(int $userId, string $tokenHash, string $expiresAt): void;

    /**
     * Busca un token de verificación de email válido (no expirado y no verificado)
     * por su hash SHA-256.
     *
     * @return array{id: int, user_id: int}|null
     */
    public function findValidEmailVerificationToken(string $tokenHash): ?array;

    /**
     * Marca un token de verificación de email como verificado (sets verified_at = NOW()).
     */
    public function markEmailVerificationTokenVerified(int $id): void;

    /**
     * Actualiza el campo email_verified_at del usuario al momento actual.
     */
    public function markUserEmailVerified(int $userId): void;

    /**
     * Indica si el usuario tiene el email verificado.
     */
    public function isUserEmailVerified(int $userId): bool;

    /**
     * Elimina los tokens de verificación de email expirados de toda la tabla.
     *
     * @return int Número de filas eliminadas
     */
    public function deleteExpiredEmailVerificationTokens(): int;

    // -------------------------------------------------------------------------
    // Password reset tokens
    // -------------------------------------------------------------------------

    /**
     * Elimina los tokens de restablecimiento de contraseña expirados y no usados
     * de un usuario específico. Se llama antes de crear un nuevo token.
     */
    public function deleteExpiredPasswordResetTokensByUser(int $userId): void;

    /**
     * Inserta un nuevo token de restablecimiento de contraseña.
     *
     * @param string $expiresAt Fecha de expiración en formato 'Y-m-d H:i:s'
     */
    public function createPasswordResetToken(
        int $userId,
        string $tokenHash,
        string $expiresAt,
        string $ipAddress,
        ?string $userAgent,
    ): void;

    /**
     * Busca un token de restablecimiento de contraseña válido
     * (no expirado y no usado) por su hash SHA-256.
     *
     * @return array{user_id: int}|null
     */
    public function findValidPasswordResetToken(string $tokenHash): ?array;

    /**
     * Marca un token de restablecimiento de contraseña como usado (sets used_at = NOW()).
     * Solo actúa si el token no ha sido usado previamente.
     *
     * @return bool true si se actualizó al menos una fila
     */
    public function markPasswordResetTokenUsed(string $tokenHash): bool;

    /**
     * Elimina los tokens de restablecimiento de contraseña expirados y no usados
     * de toda la tabla.
     *
     * @return int Número de filas eliminadas
     */
    public function deleteExpiredPasswordResetTokens(): int;
}
