<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\ProductDTO;
use App\Domain\Mappers\ProductMapper;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Override;
use PDO;
use Throwable;

final class ProductRepository extends AbstractRepository implements ProductRepositoryInterface
{
    private ProductMapper $mapper;
    public function __construct(?PDO $db = null, ?ProductMapper $mapper = null)
    {
        parent::__construct($db);
        $this->mapper = $mapper ?? new ProductMapper();
    }

    #[Override]
    protected function getTable(): string
    {
        return 'products';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return [
            'id',
            'category_id',
            'product_type',
            'name',
            'japanese_name',
            'slug',
            'description',
            'price',
            'prep_time',
            'calories',
            'attributes',
            'target_cafe_types',
            'target_animal_types',
            'duration_minutes',
            'min_pax',
            'max_pax',
            'pass_duration_minutes',
            'image_url',
            'is_active',
            'is_seasonal',
            'sort_order',
            'deleted_at',
            'created_at',
            'updated_at',
        ];
    }

    #[Override]
    public function findById(int $id): ?ProductDTO
    {
        $stmt = $this->getDb()->prepare(
            'SELECT p.id, p.name, p.slug, p.description, p.price, p.category_id, p.image_url,
                    p.is_active, p.product_type, p.min_pax, p.max_pax, p.duration_minutes,
                    p.attributes, p.target_cafe_types, p.target_animal_types, p.stock_quantity,
                    mc.name AS category_name
             FROM products p
             LEFT JOIN menu_categories mc ON p.category_id = mc.id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->mapper->toDTO($row) : null;
    }

    /**
     * Buscar producto por ID incluyendo datos de receta y producción.
     * Usar SOLO desde KitchenService y controllers KDS.
     */
    public function findWithRecipe(int $id): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, category_id, product_type, name, japanese_name, slug, description,
                    price, station, prep_time, recipe_steps, ingredients_list, critical_check,
                    calories, attributes, target_cafe_types, target_animal_types,
                    duration_minutes, min_pax, max_pax, pass_duration_minutes,
                    image_url, is_active, is_seasonal, sort_order, deleted_at, created_at, updated_at
             FROM products
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findByCafeId(int $cafeId): array
    {
        $fields = 'p.' . \implode(', p.', $this->getSelectFields());

        $sql = "
            SELECT {$fields},
                   mc.name as category_name,
                   mc.slug as category_slug,
                   c.category as cafe_category,
                   c.animal_type as cafe_animal_type
            FROM products p
            LEFT JOIN menu_categories mc ON p.category_id = mc.id
            INNER JOIN cafes c ON c.id = :cafe_id
            WHERE p.is_active = 1
              AND p.deleted_at IS NULL
              AND (
                  p.target_cafe_types IS NULL
                  OR JSON_CONTAINS(p.target_cafe_types, JSON_QUOTE(c.category))
              )
              AND (
                  p.target_animal_types IS NULL
                  OR JSON_CONTAINS(p.target_animal_types, JSON_QUOTE(c.animal_type))
              )
            ORDER BY mc.display_order, p.sort_order, p.name
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function findByCategoryId(int $categoryId, ?int $cafeId = null): array
    {
        $fields = 'p.' . \implode(', p.', $this->getSelectFields());

        if ($cafeId === null) {
            $sql = "
                SELECT {$fields}, mc.name as category_name, mc.slug as category_slug
                FROM products p
                LEFT JOIN menu_categories mc ON p.category_id = mc.id
                WHERE p.category_id = :category_id
                  AND p.is_active = 1
                  AND p.deleted_at IS NULL
                ORDER BY p.sort_order, p.name
            ";
            $params = ['category_id' => $categoryId];
        } else {
            $sql = "
                SELECT {$fields},
                       mc.name as category_name,
                       mc.slug as category_slug,
                       c.category as cafe_category,
                       c.animal_type as cafe_animal_type
                FROM products p
                LEFT JOIN menu_categories mc ON p.category_id = mc.id
                INNER JOIN cafes c ON c.id = :cafe_id
                WHERE p.category_id = :category_id
                  AND p.is_active = 1
                  AND p.deleted_at IS NULL
                  AND (
                      p.target_cafe_types IS NULL
                      OR JSON_CONTAINS(p.target_cafe_types, JSON_QUOTE(c.category))
                  )
                  AND (
                      p.target_animal_types IS NULL
                      OR JSON_CONTAINS(p.target_animal_types, JSON_QUOTE(c.animal_type))
                  )
                ORDER BY p.sort_order, p.name
            ";
            $params = ['category_id' => $categoryId, 'cafe_id' => $cafeId];
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByProductType(string $productType, ?int $cafeId = null): array
    {
        $fields = 'p.' . \implode(', p.', $this->getSelectFields());

        if ($cafeId === null) {
            $sql = "
                SELECT {$fields}, mc.name as category_name, mc.slug as category_slug
                FROM products p
                LEFT JOIN menu_categories mc ON p.category_id = mc.id
                WHERE p.product_type = :product_type
                  AND p.is_active = 1
                  AND p.deleted_at IS NULL
                ORDER BY mc.display_order, p.sort_order, p.name
            ";
            $params = ['product_type' => $productType];
        } else {
            $sql = "
                SELECT {$fields},
                       mc.name as category_name,
                       mc.slug as category_slug,
                       c.category as cafe_category
                FROM products p
                LEFT JOIN menu_categories mc ON p.category_id = mc.id
                INNER JOIN cafes c ON c.id = :cafe_id
                WHERE p.product_type = :product_type
                  AND p.is_active = 1
                  AND p.deleted_at IS NULL
                  AND (
                      p.target_cafe_types IS NULL
                      OR JSON_CONTAINS(p.target_cafe_types, JSON_QUOTE(c.category))
                  )
                  AND (
                      p.target_animal_types IS NULL
                      OR JSON_CONTAINS(p.target_animal_types, JSON_QUOTE(c.animal_type))
                  )
                ORDER BY mc.display_order, p.sort_order, p.name
            ";
            $params = ['product_type' => $productType, 'cafe_id' => $cafeId];
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPasses(?int $cafeId = null): array
    {
        return $this->findByProductType('pass', $cafeId);
    }

    public function findItems(?int $cafeId = null): array
    {
        return $this->findByProductType('item', $cafeId);
    }

    public function findAvailablePasses(): array
    {
        $stmt = $this->getDb()->query(
            "SELECT id, name, japanese_name, description, price,
                    duration_minutes, min_pax, max_pax,
                    target_cafe_types, target_animal_types,
                    attributes, image_url
             FROM products
             WHERE product_type = 'pass'
               AND is_active = 1
             ORDER BY price, duration_minutes"
        );

        return $this->decodePassJsonColumns($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Decode JSON string columns to PHP arrays/objects so json_encode produces
     * valid JS literals (avoids embedded JSON strings with unescaped quotes).
     *
     * @param array<int, array<string, mixed>> $passes
     * @return array<int, array<string, mixed>>
     */
    private function decodePassJsonColumns(array $passes): array
    {
        $jsonCols = ['target_cafe_types', 'target_animal_types', 'attributes'];
        foreach ($passes as &$pass) {
            foreach ($jsonCols as $col) {
                if (isset($pass[$col]) && \is_string($pass[$col]) && $pass[$col] !== '') {
                    $decoded = \json_decode($pass[$col], true);
                    if (\json_last_error() === JSON_ERROR_NONE) {
                        $pass[$col] = $decoded;
                    }
                }
            }
        }

        return $passes;
    }

    public function existsAndActivePass(int $productId): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id FROM products WHERE id = :id AND product_type = 'pass' AND is_active = 1"
        );
        $stmt->execute(['id' => $productId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Buscar items del carrito por sus IDs.
     * Usa parámetros posicionales para prevenir SQL injection.
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function findItemsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));

        $stmt = $this->getDb()->prepare(
            "SELECT id, name, price FROM products
             WHERE id IN ($placeholders)
             AND product_type = 'item'
             AND is_active = 1"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = \array_map('intval', $ids);
        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));

        $stmt = $this->getDb()->prepare(
            "SELECT id, name, japanese_name, price, product_type,
                    is_active, image_url, station
             FROM products
             WHERE id IN ($placeholders)"
        );
        $stmt->execute($ids);

        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[(int) $row['id']] = $row;
        }

        return $products;
    }

    /*
     * ==========================================================================
     * MÉTODOS DE GESTIÓN DE STOCK - COMPORTAMIENTO PLACEHOLDER
     * ==========================================================================
     *
     * IMPORTANTE: El sistema de control de stock NO está implementado.
     *
     * Estado Actual (Verificado 2026-02-13):
     * - La tabla 'products' NO tiene columna 'stock_quantity'
     * - Estos métodos actúan como placeholders que asumen stock ilimitado
     * - Permiten que el código compile y funcione sin errores
     * - Mantienen compatibilidad para futura implementación
     *
     * Razón del Diseño:
     * El sistema funciona sin control de stock en esta fase del MVP.
     * Los productos se consideran disponibles si están activos (is_active = 1).
     *
     * Roadmap de Implementación (cuando se requiera):
     *
     * 1. Crear migración 017_add_stock_control.sql:
     *    ALTER TABLE products
     *    ADD COLUMN stock_quantity INT UNSIGNED NULL COMMENT 'NULL = ilimitado',
     *    ADD INDEX idx_products_stock (stock_quantity);
     *
     * 2. Descomentar el código SQL en estos métodos
     *
     * 3. Crear StockService para lógica de negocio:
     *    - Alertas de stock bajo
     *    - Reservas de stock (órdenes pendientes)
     *    - Auditorías de movimientos
     *
     * 4. (Opcional) Sistema completo de inventario:
     *    - Tabla stock_movements (entradas/salidas con trazabilidad)
     *    - Tabla inventory_checks (auditorías físicas)
     *    - Tabla suppliers (proveedores)
     *    - Dashboard de inventario para Manager/Kitchen
     * ==========================================================================
     */

    /**
     * Verificar disponibilidad de stock.
     *
     * Si stock_quantity es NULL, el producto se considera ilimitado.
     * Si stock_quantity >= quantity, hay stock suficiente.
     *
     * @param int $id       ID del producto
     * @param int $quantity Cantidad solicitada
     * @return bool true si hay stock (o es ilimitado), false si no
     */
    public function hasStock(int $id, int $quantity = 1): bool
    {
        $stmt = $this->getDb()->prepare(
            'SELECT stock_quantity, is_active, deleted_at
             FROM products
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['is_active'] || $row['deleted_at'] !== null) {
            return false;
        }

        // NULL = ilimitado
        if ($row['stock_quantity'] === null) {
            return true;
        }

        return (int) $row['stock_quantity'] >= $quantity;
    }

    /**
     * Decrementar stock de forma atómica usando SELECT FOR UPDATE.
     *
     * Debe llamarse dentro de una transacción activa del caller.
     * Retorna false si no hay stock suficiente o el producto no existe.
     *
     * @param int $id       ID del producto
     * @param int $quantity Unidades a decrementar
     * @return bool true si el decremento fue exitoso
     */
    public function decrementStock(int $id, int $quantity = 1): bool
    {
        // Leer con lock pesimista para evitar race conditions
        $stmt = $this->getDb()->prepare(
            'SELECT stock_quantity
             FROM products
             WHERE id = :id
               AND is_active = 1
               AND deleted_at IS NULL
             FOR UPDATE'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        // NULL = ilimitado, no hay que actualizar nada
        if ($row['stock_quantity'] === null) {
            return true;
        }

        if ((int) $row['stock_quantity'] < $quantity) {
            return false;
        }

        $update = $this->getDb()->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity - :quantity,
                 updated_at     = NOW()
             WHERE id = :id
               AND stock_quantity >= :quantity'
        );

        return $update->execute(['id' => $id, 'quantity' => $quantity])
            && $update->rowCount() === 1;
    }

    /**
     * Incrementar stock (devolución, reposición).
     *
     * No hace nada si stock_quantity es NULL (ilimitado).
     *
     * @param int $id       ID del producto
     * @param int $quantity Unidades a reponer
     * @return bool true si la operación fue exitosa
     */
    public function incrementStock(int $id, int $quantity = 1): bool
    {
        // Verificar que el producto existe y tiene stock controlado
        $check = $this->getDb()->prepare(
            'SELECT stock_quantity
             FROM products
             WHERE id = :id
               AND is_active = 1
               AND deleted_at IS NULL'
        );
        $check->execute(['id' => $id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        // NULL = ilimitado; no hay nada que incrementar
        if ($row['stock_quantity'] === null) {
            return true;
        }

        $stmt = $this->getDb()->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity + :quantity,
                 updated_at     = NOW()
             WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'quantity' => $quantity])
            && $stmt->rowCount() === 1;
    }

    /**
     * Activar/desactivar producto.
     */
    #[Override]
    public function toggleAvailability(int $id): bool
    {
        $product = $this->findById($id);

        if (!$product) {
            return false;
        }

        return $this->update($id, [
            'is_active' => !$product->is_active,
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Obtener alérgenos de un producto.
     */
    #[Override]
    public function getAllergens(int $productId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT a.id, a.name, a.icon_class AS icon, a.icon_color, a.severity
             FROM allergens a
             INNER JOIN product_allergens pa ON a.id = pa.allergen_id
             WHERE pa.product_id = :product_id
             ORDER BY a.severity DESC, a.name '
        );
        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar productos sin alérgenos específicos.
     * Opcionalmente filtrados por café específico.
     */
    public function findWithoutAllergens(array $allergenIds, ?int $cafeId = null): array
    {
        $fields = 'p.' . \implode(', p.', $this->getSelectFields());
        $placeholders = \implode(',', \array_fill(0, \count($allergenIds), '?'));

        if ($cafeId === null) {
            $sql = "SELECT DISTINCT {$fields}, mc.name as category_name
                    FROM products p
                    LEFT JOIN menu_categories mc ON p.category_id = mc.id
                    WHERE p.is_active = 1
                    AND p.deleted_at IS NULL
                    AND p.id NOT IN (
                        SELECT pa.product_id
                        FROM product_allergens pa
                        WHERE pa.allergen_id IN ($placeholders)
                    )
                    ORDER BY mc.display_order, p.sort_order, p.name";
            $params = $allergenIds;
        } else {
            $sql = "SELECT DISTINCT {$fields},
                           mc.name as category_name,
                           c.category as cafe_category,
                           c.animal_type as cafe_animal_type
                    FROM products p
                    LEFT JOIN menu_categories mc ON p.category_id = mc.id
                    INNER JOIN cafes c ON c.id = ?
                    WHERE p.is_active = 1
                    AND p.deleted_at IS NULL
                    AND (
                        p.target_cafe_types IS NULL
                        OR JSON_CONTAINS(p.target_cafe_types, JSON_QUOTE(c.category))
                    )
                    AND (
                        p.target_animal_types IS NULL
                        OR JSON_CONTAINS(p.target_animal_types, JSON_QUOTE(c.animal_type))
                    )
                    AND p.id NOT IN (
                        SELECT pa.product_id
                        FROM product_allergens pa
                        WHERE pa.allergen_id IN ($placeholders)
                    )
                    ORDER BY mc.display_order, p.sort_order, p.name";
            $params = \array_merge($allergenIds, [$cafeId]);
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todos los productos para el panel de administración (con JSON decodificado).
     *
     * Equivalente funcional a Product::findAllAdmin() — devuelve arrays con los campos
     * target_cafe_types, attributes y target_animal_types ya decodificados como arrays.
     *
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function findAllAdmin(array $filters = [], int $limit = 100): array
    {
        $result = $this->findFiltered($filters, 1, $limit);

        foreach ($result['data'] as &$row) {
            foreach (['attributes', 'target_cafe_types', 'target_animal_types'] as $field) {
                if (\is_string($row[$field] ?? null) && $row[$field] !== '') {
                    $decoded = \json_decode($row[$field], true);
                    $row[$field] = \is_array($decoded) ? $decoded : null;
                } elseif (!isset($row[$field])) {
                    $row[$field] = null;
                }
            }
        }
        unset($row);

        return $result;
    }

    /**
     * Productos ordenables en sala (excluye pases), filtrados por café.
     */
    #[Override]
    public function findOrderableItems(int $cafeId): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT p.id, p.name, p.japanese_name, p.slug, p.price,
                    p.image_url, p.product_type, p.prep_time,
                    mc.name AS category_name
             FROM products p
             LEFT JOIN menu_categories mc ON p.category_id = mc.id
             INNER JOIN cafes c ON c.id = :cafe_id
             WHERE p.is_active = 1
               AND p.deleted_at IS NULL
               AND p.product_type != 'pass'
               AND (
                   p.target_cafe_types IS NULL
                   OR JSON_CONTAINS(p.target_cafe_types, JSON_QUOTE(c.category))
               )
               AND (
                   p.target_animal_types IS NULL
                   OR JSON_CONTAINS(p.target_animal_types, JSON_QUOTE(c.animal_type))
               )
             ORDER BY mc.display_order, p.sort_order, p.name"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar productos con filtros y paginación compleja.
     *
     * @param array $filters Filtros: category_id, product_type, is_active, search
     * @param int $page Página actual (1-based)
     * @param int $perPage Items por página
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    #[Override]
    public function findFiltered(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        // Validar parámetros
        $page = \max(1, $page);
        $perPage = \min(100, \max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        // Construir WHERE
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['product_type'])) {
            $where[] = 'p.product_type = :product_type';
            $params['product_type'] = $filters['product_type'];
        }

        if (isset($filters['is_active'])) {
            $where[] = 'p.is_active = :is_active';
            $params['is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where[] = '(p.name LIKE :search_name OR p.description LIKE :search_desc OR p.japanese_name LIKE :search_jp)';
            $params['search_name'] = $searchTerm;
            $params['search_desc'] = $searchTerm;
            $params['search_jp'] = $searchTerm;
        }

        $whereClause = \implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM products p WHERE $whereClause";
        $stmt = $this->getDb()->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos paginados
        $fields = \implode(', p.', $this->getSelectFields());
        $sql = "SELECT p.$fields, mc.name as category_name
                FROM products p
                LEFT JOIN menu_categories mc ON p.category_id = mc.id
                WHERE {$whereClause}
                ORDER BY mc.display_order, p.name
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => \max(1, (int) \ceil($total / $perPage)),
        ];
    }

    /**
     * Get all active products
     */
    public function findAllActive(): array
    {
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->query(
            "SELECT {$fields}
             FROM products
             WHERE is_active = 1
             AND deleted_at IS NULL
             ORDER BY sort_order, name"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get products by category slug
     */
    public function findByCategory(string $category): array
    {
        $fields = 'p.' . \implode(', p.', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields}, mc.name as category_name, mc.slug as category_slug
             FROM products p
             INNER JOIN menu_categories mc ON p.category_id = mc.id
             WHERE mc.slug = :category
             AND p.is_active = 1
             AND p.deleted_at IS NULL
             ORDER BY p.sort_order, p.name"
        );

        $stmt->execute(['category' => $category]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los productos con sus alérgenos normalizados en una única consulta.
     * Elimina el patrón N+1 al cargar alérgenos de todos los productos a la vez.
     *
     * Cada producto devuelto incluye 'allergens_list': array de
     * ['id' => int, 'name' => string, 'code' => string, 'severity' => string].
     *
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function getAllWithAllergens(?int $categoryId = null): array
    {
        $sql = "
            SELECT p.*,
                   GROUP_CONCAT(a.id        ORDER BY a.severity DESC, a.name SEPARATOR ',') AS allergen_ids,
                   GROUP_CONCAT(a.name      ORDER BY a.severity DESC, a.name SEPARATOR ',') AS allergen_names,
                   GROUP_CONCAT(COALESCE(a.code, '') ORDER BY a.severity DESC, a.name SEPARATOR ',') AS allergen_codes,
                   GROUP_CONCAT(a.severity  ORDER BY a.severity DESC, a.name SEPARATOR ',') AS allergen_severities
            FROM products p
            LEFT JOIN product_allergens pa ON pa.product_id = p.id
            LEFT JOIN allergens a ON a.id = pa.allergen_id
            WHERE p.is_active = 1
        ";

        $params = [];

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $sql .= '
            GROUP BY p.id
            ORDER BY p.name
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_map(static function (array $row): array {
            if ($row['allergen_ids'] !== null) {
                $ids = \explode(',', $row['allergen_ids']);
                $names = \explode(',', $row['allergen_names']);
                $codes = \explode(',', $row['allergen_codes']);
                $severities = \explode(',', $row['allergen_severities']);

                $row['allergens_list'] = \array_map(
                    static fn (string $id, string $name, string $code, string $severity): array => [
                        'id' => (int) $id,
                        'name' => $name,
                        'code' => $code,
                        'severity' => $severity,
                    ],
                    $ids,
                    $names,
                    $codes,
                    $severities
                );
            } else {
                $row['allergens_list'] = [];
            }

            unset($row['allergen_ids'], $row['allergen_names'], $row['allergen_codes'], $row['allergen_severities']);

            return $row;
        }, $rows);
    }

    /**
     * Obtiene todas las categorías de menú ordenadas por display_order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array
    {
        $stmt = $this->getDb()->query('SELECT * FROM menu_categories ORDER BY display_order');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todos los productos con nombre de categoría, sin filtro de estado.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function findAllWithCategoryName(): array
    {
        $stmt = $this->getDb()->query('
            SELECT p.*, mc.name AS category_name
            FROM products p
            LEFT JOIN menu_categories mc ON p.category_id = mc.id
            ORDER BY mc.name, p.name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar productos por texto (nombre, descripción, nombre japonés).
     *
     * @param string $query
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function search(string $query): array
    {
        $stmt = $this->getDb()->prepare('
            SELECT p.*, mc.name AS category_name
            FROM products p
            LEFT JOIN menu_categories mc ON p.category_id = mc.id
            WHERE p.name LIKE :query
               OR p.description LIKE :query
               OR p.japanese_name LIKE :query
            ORDER BY p.name
        ');

        $stmt->execute(['query' => '%' . $query . '%']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sincronizar alérgenos de un producto (DELETE + INSERT en transacción).
     *
     * @param int   $productId
     * @param int[] $allergenIds
     * @return bool
     */
    #[Override]
    public function syncAllergens(int $productId, array $allergenIds): bool
    {
        $db = $this->getDb();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('DELETE FROM product_allergens WHERE product_id = :product_id');
            $stmt->execute(['product_id' => $productId]);

            if (!empty($allergenIds)) {
                $insert = $db->prepare(
                    'INSERT INTO product_allergens (product_id, allergen_id) VALUES (:product_id, :allergen_id)'
                );

                foreach ($allergenIds as $allergenId) {
                    $insert->execute(['product_id' => $productId, 'allergen_id' => (int) $allergenId]);
                }
            }

            $db->commit();

            return true;
        } catch (Throwable $e) {
            $db->rollBack();

            return false;
        }
    }

    /**
     * Buscar productos activos sin ciertos alérgenos, con filtro opcional por categoría.
     *
     * @param int[]    $allergenIds IDs de alérgenos a excluir
     * @param int|null $categoryId  Filtrar por category_id (opcional)
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function findWithoutAllergensByCategory(array $allergenIds, ?int $categoryId = null): array
    {
        if (empty($allergenIds)) {
            return $this->findAllWithCategoryName();
        }

        $placeholders = \implode(',', \array_fill(0, \count($allergenIds), '?'));

        $sql = "SELECT DISTINCT p.*, mc.name AS category_name
                FROM products p
                LEFT JOIN menu_categories mc ON p.category_id = mc.id
                WHERE p.is_active = 1
                  AND p.deleted_at IS NULL
                  AND p.id NOT IN (
                      SELECT pa.product_id
                      FROM product_allergens pa
                      WHERE pa.allergen_id IN ($placeholders)
                  )";

        $params = $allergenIds;

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }

        $sql .= ' ORDER BY mc.name, p.name';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas para el panel admin de productos.
     *
     * @return array{total_products: int, active_products: int, category_count: int, with_allergens: int}
     */
    public function getAdminStats(): array
    {
        $row = $this->getDb()->query(
            'SELECT
                COUNT(*)                  AS total_products,
                SUM(IF(is_active = 1, 1, 0)) AS active_products,
                COUNT(DISTINCT category_id)  AS category_count,
                (SELECT COUNT(DISTINCT pa.product_id)
                 FROM product_allergens pa
                 JOIN products p2 ON pa.product_id = p2.id
                 WHERE p2.deleted_at IS NULL)  AS with_allergens
             FROM products
             WHERE deleted_at IS NULL'
        )->fetch(PDO::FETCH_ASSOC);

        return $row !== false
            ? $row
            : ['total_products' => 0, 'active_products' => 0, 'category_count' => 0, 'with_allergens' => 0];
    }
}
