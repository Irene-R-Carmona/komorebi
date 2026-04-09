<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

/**
 * Contrato para ApiTokenService.
 *
 * Define las operaciones de negocio sobre tokens Bearer opacos.
 */
interface ApiTokenServiceInterface
{
    /**
     * Genera un nuevo token opaco para el usuario y retorna el texto plano.
     * El plain token solo se devuelve en este método — nunca se vuelve a recuperar.
     */
    public function generate(int $userId, string $name, ?\DateTimeImmutable $expiresAt = null): string;

    /**
     * Valida un token opaco y retorna los datos del usuario asociado.
     * Retorna Result::fail si el token no existe, está expirado o la cuenta está inactiva.
     */
    public function validate(string $plainToken): Result;

    /**
     * Revoca un token. Verifica el ownership.
     * Retorna Result::fail si el token no existe, ya está revocado o no pertenece al usuario.
     */
    public function revoke(int $tokenId, int $userId): Result;

    /**
     * Lista los tokens activos del usuario sin exponer el hash.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array;
}
