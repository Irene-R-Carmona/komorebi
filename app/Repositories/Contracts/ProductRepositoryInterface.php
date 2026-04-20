<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Repositories\RepositoryInterface;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /**
     * Find product by ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * Obtener pases disponibles, opcionalmente filtrados por café
     *
     * @param int|null $cafeId ID del café para filtrar pases (null = todos los pases)
     * @return array<int, array<string, mixed>> Pases activos del tipo 'pass'
     */
    public function findPasses(?int $cafeId = null): array;

    /**
     * Verificar si hay stock suficiente para una cantidad dada.
     * NULL en stock_quantity significa ilimitado (siempre true para productos activos).
     *
     * @param int $id       ID del producto
     * @param int $quantity Cantidad solicitada
     */
    public function hasStock(int $id, int $quantity = 1): bool;

    /**
     * Decrementar stock de forma atómica (SELECT FOR UPDATE).
     * Debe ejecutarse dentro de una transacción activa del caller.
     *
     * @param int $id       ID del producto
     * @param int $quantity Unidades a decrementar
     * @return bool true si el decremento fue exitoso, false si no hay stock
     */
    public function decrementStock(int $id, int $quantity = 1): bool;

    /**
     * Incrementar stock (devolución, reposición).
     * No aplica si stock_quantity es NULL (ilimitado).
     *
     * @param int $id       ID del producto
     * @param int $quantity Unidades a reponer
     */
    public function incrementStock(int $id, int $quantity = 1): bool;

    /**
     * Buscar pases disponibles para reserva (sin filtro de café).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAvailablePasses(): array;

    /**
     * Verificar que un pase existe y está activo.
     *
     * @param int $productId
     * @return bool
     */
    public function existsAndActivePass(int $productId): bool;

    /**
     * Buscar items del carrito por sus IDs.
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function findItemsByIds(array $ids): array;

    /**
     * Obtener productos filtrados con paginación.
     *
     * @param array<string, mixed> $filters
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function findFiltered(array $filters = [], int $page = 1, int $perPage = 20): array;

    public function softDelete(int $id): bool;

    /**
     * Obtener todos los productos con nombre de categoría (sin filtro de estado).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllWithCategoryName(): array;

    /**
     * Alternar el estado activo de un producto.
     *
     * @param int $id ID del producto
     * @return bool true si el cambio fue exitoso
     */
    public function toggleAvailability(int $id): bool;

    /**
     * Buscar productos por ID de categoría de menú.
     *
     * @param int      $categoryId ID de menu_categories
     * @param int|null $cafeId     ID del café para filtros adicionales (opcional)
     * @return array<int, array<string, mixed>>
     */
    public function findByCategoryId(int $categoryId, ?int $cafeId = null): array;

    /**
     * Buscar productos por texto (nombre, descripción, nombre japonés).
     *
     * @param string $query Término de búsqueda
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array;

    /**
     * Sincronizar alérgenos de un producto (DELETE + INSERT en transacción).
     *
     * @param int   $productId   ID del producto
     * @param int[] $allergenIds IDs de alérgenos a asignar
     * @return bool true si la sincronización fue exitosa
     */
    public function syncAllergens(int $productId, array $allergenIds): bool;

    /**
     * Buscar productos activos que NO contienen los alérgenos indicados,
     * con filtro opcional por categoría de menú.
     *
     * @param int[]    $allergenIds IDs de alérgenos a excluir
     * @param int|null $categoryId  Filtrar por category_id (opcional)
     * @return array<int, array<string, mixed>>
     */
    public function findWithoutAllergensByCategory(array $allergenIds, ?int $categoryId = null): array;

    /**
     * Obtener todos los productos activos con sus alérgenos cargados,
     * con filtro opcional por categoría de menú.
     *
     * @param int|null $categoryId Filtrar por category_id (opcional)
     * @return array<int, array<string, mixed>>
     */
    public function getAllWithAllergens(?int $categoryId = null): array;

    /**
     * Obtener los alérgenos asignados a un producto específico.
     *
     * @param int $productId ID del producto
     * @return array<int, array<string, mixed>>
     */
    public function getAllergens(int $productId): array;

    /**
     * Buscar productos por múltiples IDs (para carrito, etc.)
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $ids): array;
}
