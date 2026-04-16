<?php

declare(strict_types=1);

namespace App\Models\Contracts;

/**
 * Contrato para el modelo de usuario (activo record).
 * Permite desacoplar servicios y tests del modelo concreto final.
 */
interface UserModelInterface
{
    public function findById(int $id): ?array;

    /** @return array<array{id: int, code: string, name: string}> */
    public function getRoles(int $userId): array;

    public function isLocked(array $user): bool;

    public function lockoutMinutesRemaining(array $user): int;

    public function verifyPassword(array $user, string $password): bool;

    public function registerFailedAttempt(int $id): void;

    public function clearLoginAttempts(int $id): void;
}
