<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Contrato para MenuRepository
 *
 * Maneja operaciones relacionadas con categorías de menú, productos,
 * pases y alérgenos.
 */
interface MenuRepositoryInterface
{
    /**
     * Obtener todas las categorías de menú ordenadas
     *
     * @param bool $includeExperiences Si incluir categoría "experiencias"
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(bool $includeExperiences = false): array;

    /**
     * Obtener productos agrupados por categoría
     *
     * @param array<int> $excludeAllergenIds IDs de alérgenos a excluir
     * @return array<int, array<string, mixed>>
     */
    public function getProductsByCategory(array $excludeAllergenIds = []): array;

    /**
     * Obtener todos los productos activos
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllProducts(): array;

    /**
     * Obtener todos los pases disponibles
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPasses(): array;

    /**
     * Obtener pases filtrados por tipo de café y animal
     *
     * @param string|null $cafeCategory Categoría del café
     * @param string|null $animalType Tipo de animal
     * @return array<int, array<string, mixed>>
     */
    public function getPassesForCafe(?string $cafeCategory = null, ?string $animalType = null): array;

    /**
     * Obtener todos los alérgenos
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllergens(): array;

    /**
     * Obtener alérgenos de un producto específico
     *
     * @param int $productId
     * @return array<int, array<string, mixed>>
     */
    public function getAllergensByProduct(int $productId): array;
}
