<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface CafeServiceInterface
{
    public function getAll(array $filters = [], int $limit = 100, int $offset = 0): array;

    public function getById(int $id): ?array;

    public function create(array $data): Result;

    public function update(int $id, array $data): Result;

    public function toggleActive(int $id): Result;

    public function delete(int $id): Result;

    public function search(string $query, int $limit = 20): array;

    public function getStats(): array|false;
}
