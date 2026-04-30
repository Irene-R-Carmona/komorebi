<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Cache;
use App\Core\Logger;
use App\Domain\DTO\ProductDTO;
use App\Exceptions\DatabaseException;
use App\Exceptions\ValidationException;
use App\Models\AuditLog;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Contracts\ProductServiceInterface;
use Override;
use PDOException;
use Throwable;

/**
 * Servicio de gestión de productos
 *
 * Encapsula la lógica de negocio relacionada con productos del menú.
 * Maneja validación, persistencia y consultas.
 */
final class ProductService extends BaseService implements ProductServiceInterface
{
    private ProductRepositoryInterface $productRepo;

    public function __construct(ProductRepositoryInterface $productRepo)
    {
        $this->productRepo = $productRepo;
    }

    /**
     * Obtiene todos los productos con sus categorías (con cache)
     *
     * @return array
     */
    #[Override]
    public function getAll(): array
    {
        $cacheKey = 'products:all';

        // Intentar obtener del cache
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Si no está en cache, consultar BD
        $products = $this->productRepo->findAllWithCategoryName();

        // Guardar en cache por 1 hora
        Cache::set($cacheKey, $products, 3600);

        return $products;
    }

    /**
     * Obtiene productos con paginación y filtros
     *
     * @param integer $page    Página actual (1-based)
     * @param integer $perPage Productos por página
     * @param array   $filters Filtros opcionales: category_id, product_type, is_active, search
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    #[Override]
    public function getAllPaginated(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $page = \max(1, $page);
        $perPage = \min(100, \max(1, $perPage));

        return $this->productRepo->findFiltered($filters, $page, $perPage);
    }

    /**
     * Obtiene un producto por ID
     *
     * @param integer $id
     * @return ProductDTO|null
     */
    #[Override]
    public function getById(int $id): ?ProductDTO
    {
        return $this->productRepo->findById($id);
    }

    /**
     * Crea un nuevo producto     *
     * @param array $data Datos validados del producto
     * @return integer ID del producto creado
     * @throws ValidationException Si faltan campos obligatorios
     * @throws DatabaseException Si falla la creación
     */
    #[Override]
    public function create(array $data): int
    {
        // Validación de campos obligatorios
        if (empty($data['name']) || empty($data['slug']) || empty($data['category_id'])) {
            throw ValidationException::multipleRequired(['name', 'slug', 'category_id']);
        }

        // Validar product_type contra ENUM DB (item, pass)
        $productType = $data['product_type'] ?? 'item';
        if (!\in_array($productType, ['item', 'pass'], true)) {
            throw ValidationException::invalidFormat('product_type', 'item|pass');
        }

        // Validar station contra ENUM DB si se proporciona
        $station = \trim($data['station'] ?? '') ?: null;
        if ($station !== null && !\in_array($station, ['bar', 'kitchen_hot', 'kitchen_cold', 'bakery', 'assembly'], true)) {
            throw ValidationException::invalidFormat('station', 'bar|kitchen_hot|kitchen_cold|bakery|assembly');
        }

        // Preparar datos con valores por defecto
        $productData = [
            'category_id' => (int) $data['category_id'],
            'product_type' => $productType,
            'name' => \trim($data['name']),
            'japanese_name' => \trim($data['japanese_name'] ?? ''),
            'slug' => \trim($data['slug']),
            'description' => \trim($data['description'] ?? ''),
            'price' => (int) ($data['price'] ?? 0),
            'image_url' => \trim($data['image_url'] ?? '') ?: null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'prep_time' => !empty($data['prep_time']) ? (int) $data['prep_time'] : null,
            'station' => $station,
            'duration_minutes' => !empty($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            'min_pax' => !empty($data['min_pax']) ? (int) $data['min_pax'] : null,
            'max_pax' => !empty($data['max_pax']) ? (int) $data['max_pax'] : null,
            'allergens' => !empty($data['allergens']) ? \json_encode($data['allergens']) : '[]',
            'attributes' => !empty($data['attributes']) ? \json_encode($data['attributes']) : '[]',
            'recipe_steps' => \trim($data['recipe_steps'] ?? '') ?: null,
            'ingredients_list' => \trim($data['ingredients_list'] ?? '') ?: '[]',
            'critical_check' => \trim($data['critical_check'] ?? '') ?: null,
        ];

        try {
            $productId = $this->productRepo->create($productData);
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e);
        }

        // Log de auditoría (best-effort: no bloquea la operación principal)
        try {
            AuditLog::log(
                'create_product',
                'product',
                $productId,
                null,
                ['name' => $productData['name'], 'category_id' => $productData['category_id'], 'price' => $productData['price']]
            );
        } catch (Throwable) {
            // Ignorar fallos del log de auditoría
        }

        // Invalidar cache
        $this->invalidateCache();

        return $productId;
    }

    /**
     * Actualiza un producto existente     *
     * @param integer $id
     * @param array   $data Datos validados del producto
     * @return boolean
     * @throws ValidationException Si faltan campos obligatorios
     * @throws DatabaseException Si falla la actualización
     */
    #[Override]
    public function update(int $id, array $data): bool
    {
        // Validación de campos obligatorios
        if (empty($data['name']) || empty($data['slug']) || empty($data['category_id'])) {
            throw ValidationException::multipleRequired(['name', 'slug', 'category_id']);
        }

        // Validar product_type contra ENUM DB (item, pass)
        $productType = $data['product_type'] ?? 'item';
        if (!\in_array($productType, ['item', 'pass'], true)) {
            throw ValidationException::invalidFormat('product_type', 'item|pass');
        }

        // Validar station contra ENUM DB si se proporciona
        $station = \trim($data['station'] ?? '') ?: null;
        if ($station !== null && !\in_array($station, ['bar', 'kitchen_hot', 'kitchen_cold', 'bakery', 'assembly'], true)) {
            throw ValidationException::invalidFormat('station', 'bar|kitchen_hot|kitchen_cold|bakery|assembly');
        }

        // Preparar datos con valores por defecto
        $productData = [
            'category_id' => (int) $data['category_id'],
            'product_type' => $productType,
            'name' => \trim($data['name']),
            'japanese_name' => \trim($data['japanese_name'] ?? ''),
            'slug' => \trim($data['slug']),
            'description' => \trim($data['description'] ?? ''),
            'price' => (int) ($data['price'] ?? 0),
            'image_url' => \trim($data['image_url'] ?? '') ?: null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'prep_time' => !empty($data['prep_time']) ? (int) $data['prep_time'] : null,
            'station' => $station,
            'duration_minutes' => !empty($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            'min_pax' => !empty($data['min_pax']) ? (int) $data['min_pax'] : null,
            'max_pax' => !empty($data['max_pax']) ? (int) $data['max_pax'] : null,
            'allergens' => !empty($data['allergens']) ? \json_encode($data['allergens']) : '[]',
            'attributes' => !empty($data['attributes']) ? \json_encode($data['attributes']) : '[]',
            'recipe_steps' => \trim($data['recipe_steps'] ?? '') ?: null,
            'ingredients_list' => \trim($data['ingredients_list'] ?? '') ?: '[]',
            'critical_check' => \trim($data['critical_check'] ?? '') ?: null,
        ];

        try {
            $success = $this->productRepo->update($id, $productData);

            // Log de auditoría
            if ($success) {
                AuditLog::log(
                    'update_product',
                    'product',
                    $id,
                    ['name' => $productData['name']],
                    ['name' => $productData['name'], 'price' => $productData['price']]
                );

                // Invalidar cache
                $this->invalidateCache();
            }

            return $success;
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e);
        }
    }

    /**
     * Elimina un producto (soft delete)     *
     * @param integer $id
     * @return boolean
     * @throws DatabaseException Si falla la eliminación
     */
    #[Override]
    public function delete(int $id): bool
    {
        try {
            $success = $this->productRepo->softDelete($id);

            // Log de auditoría
            if ($success) {
                AuditLog::log(
                    'delete_product',
                    'product',
                    $id,
                    null,
                    ['id' => $id]
                );

                // Invalidar cache
                $this->invalidateCache();
            }

            return $success;
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e);
        }
    }

    /**
     * Alterna el estado activo de un producto
     *
     * @param integer $id
     * @return boolean
     */
    #[Override]
    public function toggleActive(int $id): bool
    {
        try {
            $success = $this->productRepo->toggleAvailability($id);

            if ($success) {
                $this->invalidateCache();
            }

            return $success;
        } catch (PDOException $e) {
            Logger::error('[ProductService] Error al cambiar estado del producto', ['exception' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Obtiene productos por categoría
     *
     * @param integer $categoryId
     * @return array
     */
    #[Override]
    public function getByCategory(int $categoryId): array
    {
        return $this->productRepo->findByCategoryId($categoryId);
    }

    /**
     * Busca productos por nombre
     *
     * @param string $query
     * @return array
     */
    #[Override]
    public function search(string $query): array
    {
        return $this->productRepo->search($query);
    }

    // ─────────────────────────────────────────────────────────────
    // Gestión de Alérgenos
    // ─────────────────────────────────────────────────────────────

    /**
     * Sincroniza alérgenos de un producto
     *
     * @param integer    $productId
     * @param array<int> $allergenIds IDs de alérgenos
     * @return boolean
     * @throws DatabaseException Si falla la sincronización
     */
    #[Override]
    public function syncAllergens(int $productId, array $allergenIds): bool
    {
        $success = $this->productRepo->syncAllergens($productId, $allergenIds);

        if ($success) {
            AuditLog::log(
                'sync_product_allergens',
                'product',
                $productId,
                null,
                ['allergen_count' => \count($allergenIds)]
            );
        }

        return $success;
    }

    /**
     * Invalida el cache de productos
     */
    private function invalidateCache(): void
    {
        Cache::deletePattern('products:*');
        Cache::deletePattern('menu:*');
    }

    /**
     * Obtiene productos sin ciertos alérgenos
     *
     * @param array<int>   $excludeAllergenIds
     * @param integer|null $categoryId
     * @return array
     */
    #[Override]
    public function getWithoutAllergens(array $excludeAllergenIds, ?int $categoryId = null): array
    {
        if (empty($excludeAllergenIds)) {
            return $this->getAll();
        }

        return $this->productRepo->findWithoutAllergensByCategory($excludeAllergenIds, $categoryId);
    }

    /**
     * Obtiene productos con sus alérgenos cargados
     *
     * @param integer|null $categoryId
     * @return array
     */
    #[Override]
    public function getAllWithAllergens(?int $categoryId = null): array
    {
        return $this->productRepo->getAllWithAllergens($categoryId);
    }

    /**
     * Obtiene alérgenos de un producto
     *
     * @param integer $productId
     * @return array
     */
    #[Override]
    public function getAllergensByProduct(int $productId): array
    {
        return $this->productRepo->getAllergens($productId);
    }
}
