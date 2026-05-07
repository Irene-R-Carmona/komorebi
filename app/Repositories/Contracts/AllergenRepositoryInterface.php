<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\AllergenDTO;

/**
 * Contrato para el repositorio de alérgenos.
 * Los servicios dependen de esta interfaz, no de la implementación concreta.
 */
interface AllergenRepositoryInterface
{
    /**
     * @return array<int, AllergenDTO>
     */
    public function findAll(bool $orderBySeverity = true): array;

    /**
     * @return AllergenDTO|null
     */
    public function findById(int $id): ?AllergenDTO;

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array;

    /**
     * @param string $severity 'low' | 'medium' | 'high'
     * @return array<int, array<string, mixed>>
     */
    public function findBySeverity(string $severity): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByProduct(int $productId): array;

    /**
     * @return array<int>
     */
    public function getProductIds(int $allergenId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStatistics(): array;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function attachToProduct(int $productId, int $allergenId, ?string $notes = null): bool;

    public function detachFromProduct(int $productId, int $allergenId): bool;
}
