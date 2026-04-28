<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\AnimalIncidentDTO;

interface AnimalIncidentRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getActiveIncidents(): array;

    public function findById(int $id): ?AnimalIncidentDTO;

    public function create(array $data): int;

    public function resolve(int $id, ?string $resolution, ?int $userId): bool;
}
