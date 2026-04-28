<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\TrackerDTO;

/**
 * Contrato para el repositorio de trackers de café.
 */
interface TrackerRepositoryInterface
{
    public function findById(int $id): ?TrackerDTO;

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(int $cafeId, string $code): ?array;

    /**
     * @param string|null $status 'available' | 'in_use' | 'lost' | null (todos)
     * @return array<int, array<string, mixed>>
     */
    public function findByCafe(int $cafeId, ?string $status = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAvailable(int $cafeId): array;

    /**
     * Asigna un tracker (available → in_use). Lanza RuntimeException si no disponible.
     */
    public function assign(int $id): bool;

    /**
     * Libera un tracker (→ available).
     */
    public function release(int $id): bool;

    /**
     * Marca un tracker como perdido (→ lost).
     */
    public function markLost(int $id): bool;

    /**
     * Estadísticas por estado para un café.
     * @return array<string, int> {available: N, in_use: N, lost: N, total: N}
     */
    public function getStats(int $cafeId): array;
}
