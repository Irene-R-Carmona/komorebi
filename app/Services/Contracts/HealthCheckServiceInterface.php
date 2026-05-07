<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface HealthCheckServiceInterface
{
    public function createHealthCheck(int $animalId, int $keeperId, array $data): Result;

    public function getCheckById(int $id): ?array;

    public function getTodayDashboard(?int $cafeId = null): array;

    public function getAnimalHistory(int $animalId, int $limit = 30): array;

    public function getActiveAlerts(int $days = 7): array;

    public function hasCheckToday(int $animalId): bool;

    public function getKeeperStatistics(int $keeperId, ?string $startDate = null, ?string $endDate = null): array;

    public function detectAlerts(array $data): array;

    public function update(int $id, array $data): Result;
}
