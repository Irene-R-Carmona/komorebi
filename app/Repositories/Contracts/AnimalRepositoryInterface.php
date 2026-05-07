<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\AnimalDTO;

interface AnimalRepositoryInterface
{
    // ─── Read ────────────────────────────────────────────────

    public function findById(int $id): ?AnimalDTO;

    /** @return array<int, array<string, mixed>> */
    public function findActiveByCafe(int $cafeId): array;

    public function isAvailable(int $animalId): bool;

    public function isResting(int $animalId): bool;

    /** @return array<int, array<string, mixed>> */
    public function getAnimalsWithCafeInfoOptimized(): array;

    /**
     * @return array{total_animals: int, healthy: int, monitoring: int, sick: int, logs_today: int}
     */
    public function getHealthStatistics(): array;

    public function updateImageUrl(int $animalId, string $imageUrl): bool;

    public function countDistinctSpecies(): int;

    // ─── Write ──────────────────────────────────────────────────────────────

    public function createAnimal(array $data): int;

    public function updateAnimal(int $id, array $data): bool;

    public function softDeleteAnimal(int $id): bool;

    public function updateStatus(int $id, string $status): bool;

    /** @return array{found: bool, current_status?: string} */
    public function toggleStatus(int $id): array;
}
