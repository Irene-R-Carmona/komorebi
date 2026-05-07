<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AnimalCareServiceInterface
{
    public function getAllAnimals(): array;

    public function getAnimalById(int $id): ?array;

    public function createAnimal(array $data): Result;

    public function updateAnimal(int $id, array $data): Result;

    public function deleteAnimal(int $id): Result;

    public function getDashboardData(): array;

    public function getAnimalsWithCafeInfo(): array;

    public function getStatistics(): array;

    public function getRecentLogs(int $limit = 20): array;

    public function getActiveIncidents(): array;

    public function getIncidentById(int $id): ?array;

    public function createCareLog(array $data): Result;

    public function updateHealth(int $animalId, string $healthStatus, ?string $notes = null, ?int $userId = null): Result;

    public function toggleActive(int $animalId): Result;

    public function createIncident(array $data): Result;

    public function resolveIncident(int $incidentId, ?string $resolution = null, ?int $userId = null): Result;

    public function updateIncident(int $id, array $data): Result;
}
