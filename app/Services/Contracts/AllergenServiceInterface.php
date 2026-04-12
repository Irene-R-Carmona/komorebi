<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AllergenServiceInterface
{
    public function listAll(bool $orderBySeverity = true): array;

    public function getById(int $id): ?array;

    public function getByName(string $name): ?array;

    public function getByProduct(int $productId): Result;

    public function getProductIds(int $allergenId): Result;

    public function getStatistics(): array;

    public function create(array $data): Result;

    public function update(int $id, array $data): Result;

    public function attachToProduct(int $productId, int $allergenId, ?string $notes = null): Result;

    public function detachFromProduct(int $productId, int $allergenId): Result;
}
