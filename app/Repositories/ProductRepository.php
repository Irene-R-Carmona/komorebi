<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\ProductRepositoryInterface;
use PDO;

/**
 * Repositorio de Productos.
 *
 * Encapsula la lógica de acceso a datos de productos del menú,
 * incluyendo pases, bebidas, comidas y merchandising.
 */
final class ProductRepository extends AbstractRepository implements ProductRepositoryInterface
{
    #[\Override]
    protected function getTable(): string
    {
        return 'products';
    }

    #[\Override]
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
            'station',
            'prep_time',
            'recipe_steps',
            'ingredients_list',
            'critical_check',
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

    /**
     * Buscar productos disponibles para un café específico.
     * Filtra por target_cafe_types y target_animal_types usando JSON.
     *
     * @param int $cafeId ID del café
     * @return array Productos disponibles para ese café
     */
    public function findByCafeId(int $cafeId): array
    {
        $fields = 'p.' . implode(', p.', $this->getSelectFields());

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

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar productos por categoría de menú (ID de menu_categories).
     * Opcionalmente filtrados por café específico.
     *
     * @param int $categoryId ID de menu_categories
     * @param int|null $cafeId ID del café (opcional)
     * @return array
     */
    public function findByCategoryId(int $categoryId, ?int $cafeId = null): array
    {
        $fields = 'p.' . implode(', p.', $this->getSelectFields());

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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar productos por tipo (pass o item).
     * Opcionalmente filtrados por café específico.
     *
     * @param string $productType 'pass' o 'item'
     * @param int|null $cafeId ID del café (opcional)
     * @return array
     */
    public function findByProductType(string $productType, ?int $cafeId = null): array
    {
        $fields = 'p.' . implode(', p.', $this->getSelectFields());

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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar pases (passes) disponibles.
     */
    public function findPasses(?int $cafeId = null): array
    {
        return $this->findByProductType('pass', $cafeId);
    }

    /**
     * Buscar items (productos vendibles).
     */
    public function findItems(?int $cafeId = null): array
    {
        return $this->findByProductType('item', $cafeId);
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
        $stmt = $this->db->prepare(
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
        $stmt = $this->db->prepare(
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

        $update = $this->db->prepare(
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
        $check = $this->db->prepare(
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

        $stmt = $this->db->prepare(
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
    public function toggleAvailability(int $id): bool
    {
        $product = $this->findById($id);

        if (!$product) {
            return false;
        }

        return $this->update($id, [
            'is_active' => !$product['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Obtener alérgenos de un producto.
     */
    public function getAllergens(int $productId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, a.name, a.icon, a.severity
             FROM allergens a
             INNER JOIN product_allergens pa ON a.id = pa.allergen_id
             WHERE pa.product_id = :product_id
             ORDER BY a.severity DESC, a.name "
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
        $fields = 'p.' . implode(', p.', $this->getSelectFields());
        $placeholders = implode(',', array_fill(0, count($allergenIds), '?'));

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
            $params = array_merge($allergenIds, [$cafeId]);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

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
    public function findFiltered(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        // Validar parámetros
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
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

        $whereClause = implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM products p WHERE $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos paginados
        $fields = implode(', p.', $this->getSelectFields());
        $sql = "SELECT p.$fields, mc.name as category_name
                FROM products p
                LEFT JOIN menu_categories mc ON p.category_id = mc.id
                WHERE {$whereClause}
                ORDER BY mc.display_order, p.name
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

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
            'totalPages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Get all active products
     */
    public function findAllActive(): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->query(
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
        $fields = 'p.' . implode(', p.', $this->getSelectFields());

        $stmt = $this->db->prepare(
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
    public function getAllWithAllergens(): array
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
            GROUP BY p.id
            ORDER BY p.name
        ";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            if ($row['allergen_ids'] !== null) {
                $ids        = explode(',', $row['allergen_ids']);
                $names      = explode(',', $row['allergen_names']);
                $codes      = explode(',', $row['allergen_codes']);
                $severities = explode(',', $row['allergen_severities']);

                $row['allergens_list'] = array_map(
                    static fn(string $id, string $name, string $code, string $severity): array => [
                        'id'       => (int) $id,
                        'name'     => $name,
                        'code'     => $code,
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
        $stmt = $this->db->query('SELECT * FROM menu_categories ORDER BY display_order');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
