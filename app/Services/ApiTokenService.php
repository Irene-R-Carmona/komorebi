<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Result;
use App\Repositories\Contracts\ApiTokenRepositoryInterface;
use App\Services\Contracts\ApiTokenServiceInterface;
use PDO;
use Throwable;

/**
 * Servicio de tokens Bearer opacos para la API.
 *
 * El token en texto plano solo existe en memoria durante `generate()`.
 * En base de datos se persiste únicamente su hash SHA-256.
 */
final class ApiTokenService implements ApiTokenServiceInterface
{
    public function __construct(private readonly ApiTokenRepositoryInterface $repository) {}

    /**
     * Genera un nuevo token para el usuario y retorna el texto plano.
     *
     * ⚠ El plain token solo se devuelve aquí — no volver a recuperarlo.
     */
    public function generate(int $userId, string $name, ?\DateTimeImmutable $expiresAt = null): string
    {
        $plain = bin2hex(random_bytes(32));   // 64 hex chars, 256-bit entropy
        $hash  = hash('sha256', $plain);

        $this->repository->createToken($userId, $name, $hash, $expiresAt);

        return $plain;
    }

    /**
     * Valida un token opaco y retorna los datos del usuario asociado.
     *
     * @return Result
     */
    public function validate(string $plainToken): Result
    {
        $hash = hash('sha256', $plainToken);
        $row  = $this->repository->findByHash($hash);

        if ($row === null) {
            return Result::fail('Token inválido o expirado.', 'invalid_token');
        }

        $userId = (int) $row['user_id'];

        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare('SELECT id, name, email, is_active FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            Logger::error('[ApiTokenService] Error al cargar usuario del token', [
                'token_id' => $row['id'],
                'error'    => $e->getMessage(),
            ]);
            return Result::fail('Error interno al validar el token.', 'server_error');
        }

        if ($user === null || !(bool) $user['is_active']) {
            return Result::fail('Cuenta desactivada o no encontrada.', 'account_disabled');
        }

        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare(
                'SELECT r.code FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?'
            );
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            Logger::error('[ApiTokenService] Error al cargar roles del token', [
                'token_id' => $row['id'],
                'error'    => $e->getMessage(),
            ]);
            $roles = ['user'];
        }

        // Best-effort: actualizar last_used_at (no propagar excepciones)
        try {
            $this->repository->updateLastUsed((int) $row['id']);
        } catch (Throwable) {
            // ignorar — no bloquear la autenticación
        }

        return Result::ok([
            'user_id'    => (int) $user['id'],
            'user'       => $user,
            'user_roles' => $roles,
            'token_id'   => (int) $row['id'],
        ]);
    }

    /**
     * Revoca un token asegurando que pertenezca al usuario indicado.
     */
    public function revoke(int $tokenId, int $userId): Result
    {
        $revoked = $this->repository->revoke($tokenId, $userId);

        if (!$revoked) {
            return Result::fail('Token no encontrado o ya revocado.', 'not_found');
        }

        return Result::ok(true);
    }

    /**
     * Lista los tokens activos de un usuario sin exponer el hash.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return $this->repository->listForUser($userId);
    }
}
