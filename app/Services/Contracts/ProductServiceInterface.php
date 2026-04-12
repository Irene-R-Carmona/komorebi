<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ProductServiceInterface
{
    public function getAll(): array;

    public function getAllPaginated(int $page = 1, int $perPage = 20, array $filters = []): array;

    public function getById(int $id): ?array;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function toggleActive(int $id): bool;

    public function getByCategory(int $categoryId): array;

    public function search(string $query): array;

    public function syncAllergens(int $productId, array $allergenIds): bool;

    public function getWithoutAllergens(array $excludeAllergenIds, ?int $categoryId = null): array;

    public function getAllWithAllergens(?int $categoryId = null): array;

    public function getAllergensByProduct(int $productId): array;
}
