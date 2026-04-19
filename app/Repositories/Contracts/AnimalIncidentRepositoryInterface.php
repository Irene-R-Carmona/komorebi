<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AnimalIncidentRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getActiveIncidents(): array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    public function create(array $data): int;

    public function resolve(int $id, ?string $resolution, ?int $userId): bool;
}
