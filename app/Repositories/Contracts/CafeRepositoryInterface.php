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
     * @return array<int, array<string, mixed>>
     */
    public function findFiltered(array $filters): array;

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
}
