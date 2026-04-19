<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Logger;
use App\Core\TransactionalService;
use App\Exceptions\DatabaseException;
use App\Exceptions\ValidationException;
use App\Models\AuditLog;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Contracts\ProductServiceInterface;
use Override;
use PDO;
use PDOException;

/**
 * Servicio de gestión de productos
 *
 * Encapsula la lógica de negocio relacionada con productos del menú.
 * Maneja validación, persistencia y consultas.
 */
final class ProductService extends TransactionalService implements ProductServiceInterface
{
    private ProductRepositoryInterface $productRepo;

    public function __construct(ProductRepositoryInterface $productRepo, ?PDO $pdo = null)
    {
        parent::__construct($pdo ?? Database::getConnection());
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
        $stmt = $this->db->query('
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN menu_categories c ON p.category_id = c.id
            ORDER BY c.name, p.name
        ');

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
     * @return array|null
     */
    #[Override]
    public function getById(int $id): ?array
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

        // Preparar datos con valores por defecto
        $productData = [
            'category_id' => (int) $data['category_id'],
            'product_type' => $data['product_type'] ?? 'food',
            'name' => \trim($data['name']),
            'japanese_name' => \trim($data['japanese_name'] ?? ''),
            'slug' => \trim($data['slug']),
            'description' => \trim($data['description'] ?? ''),
            'price' => (int) ($data['price'] ?? 0),
            'image_url' => \trim($data['image_url'] ?? '') ?: null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'prep_time' => !empty($data['prep_time']) ? (int) $data['prep_time'] : null,
            'station' => \trim($data['station'] ?? '') ?: null,
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

            // Log de auditoría
            AuditLog::log(
                'create_product',
                'product',
                $productId,
                null,
                ['name' => $productData['name'], 'category_id' => $productData['category_id'], 'price' => $productData['price']]
            );

            // Invalidar cache
            $this->invalidateCache();

            return $productId;
        } catch (PDOException $e) {
            throw DatabaseException::fromPDOException($e);
        }
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

        // Preparar datos con valores por defecto
        $productData = [
            'category_id' => (int) $data['category_id'],
            'product_type' => $data['product_type'] ?? 'food',
            'name' => \trim($data['name']),
            'japanese_name' => \trim($data['japanese_name'] ?? ''),
            'slug' => \trim($data['slug']),
            'description' => \trim($data['description'] ?? ''),
            'price' => (int) ($data['price'] ?? 0),
            'image_url' => \trim($data['image_url'] ?? '') ?: null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'prep_time' => !empty($data['prep_time']) ? (int) $data['prep_time'] : null,
            'station' => \trim($data['station'] ?? '') ?: null,
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
            $sql = 'UPDATE products SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id';

            $success = $this->db->prepare($sql)->execute(['id' => $id]);

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
        $stmt = $this->db->prepare('
            SELECT * FROM products
            WHERE category_id = :category_id
            AND is_active = 1
            ORDER BY name
        ');

        $stmt->execute(['category_id' => $categoryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt = $this->db->prepare('
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN menu_categories c ON p.category_id = c.id
            WHERE p.name LIKE :query
            OR p.description LIKE :query
            OR p.japanese_name LIKE :query
            ORDER BY p.name
        ');

        $stmt->execute(['query' => "%$query%"]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        try {
            $this->db->beginTransaction();

            // Eliminar alérgenos actuales
            $stmt = $this->db->prepare('DELETE FROM product_allergens WHERE product_id = :product_id');
            $stmt->execute(['product_id' => $productId]);

            // Insertar nuevos alérgenos
            if (!empty($allergenIds)) {
                $stmt = $this->db->prepare('
                    INSERT INTO product_allergens (product_id, allergen_id)
                    VALUES (:product_id, :allergen_id)
                ');

                foreach ($allergenIds as $allergenId) {
                    $stmt->execute([
                        'product_id' => $productId,
                        'allergen_id' => $allergenId,
                    ]);
                }
            }

            $this->db->commit();

            // Log de auditoría
            AuditLog::log(
                'sync_product_allergens',
                'product',
                $productId,
                null,
                ['allergen_count' => \count($allergenIds)]
            );

            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw DatabaseException::fromPDOException($e);
        }
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

        $placeholders = \implode(',', \array_fill(0, \count($excludeAllergenIds), '?'));

        $sql = "SELECT DISTINCT p.*, c.name as category_name
                FROM products p
                LEFT JOIN menu_categories c ON p.category_id = c.id
                WHERE p.is_active = 1
                  AND p.id NOT IN (
                      SELECT product_id
                      FROM product_allergens
                      WHERE allergen_id IN ($placeholders)
                  )";

        $params = $excludeAllergenIds;

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }

        $sql .= ' ORDER BY c.name, p.name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $sql = 'SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN menu_categories c ON p.category_id = c.id
                WHERE p.is_active = 1';

        $params = [];

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $sql .= ' ORDER BY c.name, p.name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cargar alérgenos para cada producto
        foreach ($products as &$product) {
            $product['allergens_list'] = $this->getAllergensByProduct((int) $product['id']);
        }

        return $products;
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
        $stmt = $this->db->prepare('
            SELECT a.*
            FROM allergens a
            JOIN product_allergens pa ON a.id = pa.allergen_id
            WHERE pa.product_id = :product_id
            ORDER BY
                CASE a.severity
                    WHEN "high" THEN 1
                    WHEN "medium" THEN 2
                    WHEN "low" THEN 3
                END,
                a.name
        ');

        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
