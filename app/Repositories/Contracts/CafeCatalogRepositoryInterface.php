<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface CafeCatalogRepositoryInterface extends CafeRepositoryInterface
{
    /**
     * Listado con filtros opcionales y orden configurable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllFiltered(
        ?string $category = null,
        ?string $animalType = null,
        string $orderBy = 'name',
        string $order = 'ASC'
    ): array;

    /**
     * Obtiene un café con sus animales activos embebidos.
     *
     * @return array<string, mixed>|null
     */
    public function findWithAnimals(string $slug): ?array;

    /**
     * Obtiene las zonas de un café.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getZones(int $cafeId): array;

    /**
     * Número de veces que un café ha sido marcado como favorito.
     */
    public function getFavoritesCount(int $cafeId): int;
}
