<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface CafeRepositoryInterface
{
    /**
     * Find cafe by ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * Find cafe by slug
     *
     * @param string $slug
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array;

    /**
     * Get all active cafes
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActive(): array;

    /**
     * Find cafes by category
     *
     * @param string $category
     * @return array<int, array<string, mixed>>
     */
    public function findByCategory(string $category): array;

    /**
     * Find cafes by animal type
     *
     * @param string $animalType
     * @return array<int, array<string, mixed>>
     */
    public function findByAnimalType(string $animalType): array;

    /**
     * Find cafes with filters
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @param int $offset
     * @return array<int, array<string, mixed>>
     */
    public function findFiltered(array $filters, int $limit = 100, int $offset = 0): array;

    /**
     * Check if cafe has available capacity
     *
     * @param int $cafeId
     * @param string $date
     * @param string $time
     * @return bool
     */
    public function hasAvailableCapacity(int $cafeId, string $date, string $time): bool;

    /**
     * Update cafe fields
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Create a new cafe
     *
     * @param array<string, mixed> $data
     * @return int ID of created cafe
     */
    public function create(array $data): int;

    /**
     * Soft delete a cafe
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Soft delete a cafe (sets deleted_at)
     *
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool;

    /**
     * Buscar cafés disponibles para reserva.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAvailableForReservation(): array;

    /**
     * Buscar cafés disponibles para reserva, indexados por ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAvailableForReservationById(): array;

    /**
     * Verificar que un café existe y está activo.
     *
     * @param int $cafeId
     * @return bool
     */
    public function existsAndActive(int $cafeId): bool;

    /**
     * Recalcular y actualizar el rating promedio de un café.
     *
     * @param int $id
     * @return bool
     */
    public function updateRating(int $id): bool;

    /**
     * Búsqueda de texto libre en nombre, ubicación y descripción.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array;

    /**
     * Buscar cafés por múltiples IDs.
     *
     * @param array<int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $ids): array;
}
