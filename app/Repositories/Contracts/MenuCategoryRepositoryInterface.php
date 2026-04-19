<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface MenuCategoryRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function findAll(): array;

    /** @return array{id: int, name: string, slug: string, display_order: int}|null */
    public function findBySlug(string $slug): ?array;

    /** @return array<int, array<string, mixed>> Incluye product_count y available_count */
    public function findAllWithProductCount(): array;
}
