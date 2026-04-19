<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Repositories\Contracts\ApiTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\ApiTokenServiceInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Servicio de tokens Bearer opacos para la API.
 *
 * El token en texto plano solo existe en memoria durante `generate()`.
 * En base de datos se persiste únicamente su hash SHA-256.
 */
final class ApiTokenService implements ApiTokenServiceInterface
{
    public function __construct(
        private readonly ApiTokenRepositoryInterface $repository,
        private readonly UserRepositoryInterface $userRepo,
    ) {
    }

    /**
     * Genera un nuevo token para el usuario y retorna el texto plano.
     *
     * ⚠ El plain token solo se devuelve aquí — no volver a recuperarlo.
     */
    public function generate(int $userId, string $name, ?DateTimeImmutable $expiresAt = null): string
    {
        $plain = \bin2hex(\random_bytes(32));   // 64 hex chars, 256-bit entropy
        $hash = \hash('sha256', $plain);

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
        $hash = \hash('sha256', $plainToken);
        $row = $this->repository->findByHash($hash);

        if ($row === null) {
            return Result::fail('Token inválido o expirado.', 'invalid_token');
        }

        return $this->buildTokenResult($row);
    }

    /** @param array<string, mixed> $row */
    private function buildTokenResult(array $row): Result
    {
        $userId = (int) $row['user_id'];

        try {
            $user = $this->userRepo->findById($userId);

            if ($user === null || !(bool) $user['is_active']) {
                return Result::fail('Cuenta desactivada o no encontrada.', 'account_disabled');
            }

            $roles = \array_column($this->userRepo->getRoles($userId), 'slug');
        } catch (Throwable $e) {
            Logger::error('[ApiTokenService] Error al cargar datos del token', [
                'token_id' => $row['id'],
                'error' => $e->getMessage(),
            ]);

            return Result::fail('Error interno al validar el token.', 'server_error');
        }

        try {
            $this->repository->updateLastUsed((int) $row['id']);
        } catch (Throwable) {
            // best-effort — do not block authentication on tracking failure
        }

        return Result::ok([
            'user_id' => (int) $user['id'],
            'user' => $user,
            'user_roles' => $roles,
            'token_id' => (int) $row['id'],
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
