<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface FavoriteRepositoryInterface
{
    public function add(int $userId, int $cafeId): bool;

    public function remove(int $userId, int $cafeId): bool;

    /** @return bool True si se añadió, false si se eliminó */
    public function toggle(int $userId, int $cafeId): bool;

    public function exists(int $userId, int $cafeId): bool;

    /** @return array<int> IDs de cafés favoritos del usuario */
    public function getCafeIds(int $userId): array;

    /** @return array<int, array<string, mixed>> Cafés favoritos con detalle */
    public function getByUser(int $userId): array;

    public function countByUser(int $userId): int;

    /** @return array<int, array<string, mixed>> */
    public function getUsersByCafe(int $cafeId): array;

    /** @return array<int, array<string, mixed>> */
    public function getMostPopular(int $limit = 10): array;
}
