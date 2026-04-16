<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface RecentlyViewedServiceInterface
{
    public function add(int $cafeId): bool;

    /** @return array<int> */
    public function getAll(): array;

    public function clear(): bool;

    public function getMaxItems(): int;
}
