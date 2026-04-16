<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Models\Traits\ValidatesData;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo Product
 *
 * Gestiona productos del menú (items y pases de experiencia).
 */
final class Product
{
    use ValidatesData;

    private ?PDO $db = null;

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Tipos de producto */
    public const string TYPE_ITEM = 'item';
    public const string TYPE_PASS = 'pass';

    /** Estaciones de preparación */
    public const string STATION_BAR = 'bar';
    public const string STATION_KITCHEN_HOT = 'kitchen_hot';
    public const string STATION_KITCHEN_COLD = 'kitchen_cold';
    public const string STATION_BAKERY = 'bakery';
    public const string STATION_ASSEMBLY = 'assembly';

    public const array VALID_STATIONS = [
        self::STATION_BAR,
        self::STATION_KITCHEN_HOT,
        self::STATION_KITCHEN_COLD,
        self::STATION_BAKERY,
        self::STATION_ASSEMBLY,
    ];

    /** Campos para SELECT */
    private const array SELECT_FIELDS = [
        'p.id',
        'p.category_id',
        'p.product_type',
        'p.name',
        'p.japanese_name',
        'p.description',
        'p.price',
        'p.station',
        'p.prep_time',
        'p.duration_minutes',
        'p.min_pax',
        'p.max_pax',
        'p.attributes',
        'p.image_url',
        'p.is_active',
        'p.deleted_at',
    ];

    /** Campos para KDS (cocina) */
    private const array KDS_FIELDS = [
        'p.id',
        'p.name',
        'p.station',
        'p.prep_time',
        'p.recipe_steps',
        'p.ingredients_list',
        'p.critical_check',
    ];

    // ─────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    // Búsqueda
    // ─────────────────────────────────────────────────────────────

    /**
     * Busca un producto por ID.
     */
    public function findById(int $id): ?array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields, c.name AS category_name, c.slug AS category_slug
                FROM products p
                JOIN menu_categories c ON c.id = p.category_id
                WHERE p.id = :id LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        if ($product) {
            $product = $this->decodeJsonFields($product);
        }

        return $product ?: null;
    }

    /**
     * Busca múltiples productos por IDs.
     *
     * @param array<int> $ids
     * @return array<int, array> Indexado por ID
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = \array_map('intval', $ids);
        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));

        $sql = "SELECT id, name, japanese_name, price, product_type,
                   is_active, image_url, station
            FROM products
            WHERE id IN ($placeholders)";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($ids);

        $products = [];
        while ($row = $stmt->fetch()) {
            $products[(int) $row['id']] = $row;
        }

        return $products;
    }

    /**
     * Obtiene un producto con información para KDS.
     */
    public function findForKds(int $id): ?array
    {
        $fields = \implode(', ', self::KDS_FIELDS);

        $stmt = $this->getDb()->prepare(
            "SELECT $fields FROM products p WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        if ($product) {
            $product['ingredients_list'] = \json_decode($product['ingredients_list'] ?? '[]', true);
        }

        return $product ?: null;
    }

    // ─────────────────────────────────────────────────────────────
    // Listados
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todos los productos disponibles.
     */
    public function findAvailable(?string $categorySlug = null): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);
        $where = ['p.is_active = 1', "p.product_type = 'item'"];
        $params = [];

        if ($categorySlug !== null) {
            $where[] = 'c.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }

        $whereClause = \implode(' AND ', $where);

        $sql = "SELECT $fields, c.name AS category_name, c.slug AS category_slug
                FROM products p
                JOIN menu_categories c ON c.id = p.category_id
                WHERE {$whereClause}
                ORDER BY c.display_order , p.name ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return \array_map([$this, 'decodeJsonFields'], $stmt->fetchAll());
    }

    /**
     * Obtiene el menú completo agrupado por categorías.
     */
    public function getFullMenu(): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields, c.name AS category_name, c.slug AS category_slug
                FROM products p
                JOIN menu_categories c ON c.id = p.category_id
                WHERE p.is_active = 1 AND p.product_type = 'item'
                ORDER BY c.display_order , p.name ";

        $stmt = $this->getDb()->query($sql);
        $products = $stmt->fetchAll();

        // Agrupar por categoría
        $menu = [];
        foreach ($products as $product) {
            $product = $this->decodeJsonFields($product);
            $categorySlug = $product['category_slug'];

            if (!isset($menu[$categorySlug])) {
                $menu[$categorySlug] = [
                    'name' => $product['category_name'],
                    'slug' => $categorySlug,
                    'products' => [],
                ];
            }

            $menu[$categorySlug]['products'][] = $product;
        }

        return \array_values($menu);
    }

    /**
     * Obtiene los pases de experiencia disponibles.
     */
    public function findPasses(?string $cafeCategory = null, ?string $animalType = null): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT {$fields}
                FROM products p
                WHERE p.is_active = 1 AND p.product_type = 'pass'
                ORDER BY p.price ";

        $stmt = $this->getDb()->query($sql);
        $passes = \array_map([$this, 'decodeJsonFields'], $stmt->fetchAll());

        // Filtrar por compatibilidad (si se especifica)
        if ($cafeCategory !== null || $animalType !== null) {
            $passes = \array_filter($passes, static function ($pass) use ($cafeCategory, $animalType) {
                // Verificar target_cafe_types
                if ($cafeCategory !== null && !empty($pass['target_cafe_types']) && !\in_array($cafeCategory, $pass['target_cafe_types'], true)) {
                    return false;
                }
                // Verificar target_animal_types
                if ($animalType !== null && !empty($pass['target_animal_types']) && !\in_array($animalType, $pass['target_animal_types'], true)) {
                    return false;
                }

                return true;
            });

            $passes = \array_values($passes);
        }

        return $passes;
    }

    /**
     * Obtiene productos por estación (para KDS).
     */
    public function findByStation(string $station): array
    {
        $this->validateInArray($station, self::VALID_STATIONS, 'station');

        $stmt = $this->getDb()->prepare(
            "SELECT id, name, prep_time, recipe_steps, critical_check
             FROM products
             WHERE station = :station AND is_active = 1 AND product_type = 'item'
             ORDER BY name "
        );
        $stmt->execute(['station' => $station]);

        return $stmt->fetchAll();
    }

    /**
     * Búsqueda de productos por texto.
     */
    public function search(string $query, int $limit = 20): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);
        $query = '%' . $this->sanitizeString($query, 100) . '%';

        $sql = "SELECT $fields, c.name AS category_name
                FROM products p
                JOIN menu_categories c ON c.id = p.category_id
                WHERE p.is_active = 1
                  AND (p.name LIKE :q OR p.japanese_name LIKE :q OR p.description LIKE :q)
                ORDER BY p.name
                LIMIT :limit";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('q', $query);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return \array_map([$this, 'decodeJsonFields'], $stmt->fetchAll());
    }

    // ─────────────────────────────────────────────────────────────
    // Filtros especiales
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene productos filtrados por alérgenos (excluyendo).
     *
     * @param array<string> $excludeAllergens Alérgenos a excluir
     */
    public function findWithoutAllergens(array $excludeAllergens): array
    {
        $products = $this->findAvailable();

        return \array_filter($products, static function ($product) use ($excludeAllergens) {
            $productAllergens = $product['allergens'] ?? [];

            return \array_all($excludeAllergens, static fn ($allergen) => !\in_array($allergen, $productAllergens, true));
        });
    }

    /**
     * Obtiene productos con un atributo específico.
     *
     * @param string $attribute Ej: 'vegan', 'spicy', 'popular'
     */
    public function findWithAttribute(string $attribute): array
    {
        $products = $this->findAvailable();

        return \array_values(\array_filter($products, static function ($product) use ($attribute) {
            $attributes = $product['attributes'] ?? [];

            return \in_array($attribute, $attributes, true);
        }));
    }

    // ─────────────────────────────────────────────────────────────
    // Administración
    // ─────────────────────────────────────────────────────────────

    /**
     * Lista todos los productos (admin/manager).
     */
    public function findAllAdmin(
        ?int $categoryId = null,
        ?bool $available = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $fields = \implode(', ', self::SELECT_FIELDS);
        $where = ['1=1'];
        $params = [];

        if ($categoryId !== null) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($available !== null) {
            $where[] = 'p.is_active = :available';
            $params['available'] = $available ? 1 : 0;
        }

        $whereClause = \implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM products p WHERE $whereClause";
        $stmt = $this->getDb()->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos
        $sql = "SELECT $fields, c.name AS category_name
                FROM products p
                JOIN menu_categories c ON c.id = p.category_id
                WHERE {$whereClause}
                ORDER BY c.display_order , p.name
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => \array_map([$this, 'decodeJsonFields'], $stmt->fetchAll()),
            'total' => $total,
        ];
    }

    /**
     * Activa/desactiva un producto.
     */
    public function toggleAvailability(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE products SET is_active = NOT is_active WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Actualiza el precio de un producto.
     */
    public function updatePrice(int $id, int $price): bool
    {
        if ($price < 0) {
            throw new RuntimeException('El precio no puede ser negativo.');
        }

        $stmt = $this->getDb()->prepare(
            'UPDATE products SET price = :price WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'price' => $price]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Decodifica campos JSON del producto.
     */
    private function decodeJsonFields(array $product): array
    {
        $jsonFields = ['allergens', 'attributes', 'target_cafe_types', 'target_animal_types'];

        foreach ($jsonFields as $field) {
            if (isset($product[$field]) && \is_string($product[$field])) {
                $product[$field] = \json_decode($product[$field], true) ?? [];
            }
        }

        return $product;
    }

    // ─────────────────────────────────────────────────────────────
    // Gestión de Alérgenos (tabla normalizada)
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene alérgenos de un producto desde tabla normalizada.
     *
     * @param integer $productId
     * @return array<array> Lista de alérgenos con toda su info
     */
    public function getAllergensNormalized(int $productId): array
    {
        $stmt = $this->getDb()->prepare('
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

        return $stmt->fetchAll();
    }

    /**
     * Sincroniza alérgenos de un producto.
     *
     * @param integer    $productId
     * @param array<int> $allergenIds IDs de alérgenos a asociar
     */
    public function syncAllergens(int $productId, array $allergenIds): void
    {
        // Eliminar asociaciones actuales
        $stmt = $this->getDb()->prepare('DELETE FROM product_allergens WHERE product_id = :product_id');
        $stmt->execute(['product_id' => $productId]);

        // Insertar nuevas asociaciones
        if (!empty($allergenIds)) {
            $stmt = $this->getDb()->prepare('
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
    }

    /**
     * Agrega un alérgeno a un producto.
     */
    public function addAllergen(int $productId, int $allergenId): bool
    {
        try {
            $stmt = $this->getDb()->prepare('
                INSERT IGNORE INTO product_allergens (product_id, allergen_id)
                VALUES (:product_id, :allergen_id)
            ');

            return $stmt->execute([
                'product_id' => $productId,
                'allergen_id' => $allergenId,
            ]);
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Elimina un alérgeno de un producto.
     */
    public function removeAllergen(int $productId, int $allergenId): bool
    {
        $stmt = $this->getDb()->prepare('
            DELETE FROM product_allergens
            WHERE product_id = :product_id AND allergen_id = :allergen_id
        ');

        return $stmt->execute([
            'product_id' => $productId,
            'allergen_id' => $allergenId,
        ]);
    }

    /**
     * Obtiene productos SIN ciertos alérgenos (usando tabla normalizada).
     *
     * @param array<int>   $excludeAllergenIds IDs de alérgenos a excluir
     * @param integer|null $categoryId         Filtrar por categoría
     * @return array<array>
     */
    public function findWithoutAllergensNormalized(array $excludeAllergenIds, ?int $categoryId = null): array
    {
        if (empty($excludeAllergenIds)) {
            return $this->findAvailable();
        }

        $placeholders = \implode(',', \array_fill(0, \count($excludeAllergenIds), '?'));
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields, c.name AS category_name
                FROM products p
                JOIN menu_categories c ON c.id = p.category_id
                WHERE p.is_active = 1
                  AND p.product_type = 'item'
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

        $sql .= ' ORDER BY c.display_order, p.name';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return \array_map([$this, 'decodeJsonFields'], $stmt->fetchAll());
    }

    /**
     * Obtiene estadísticas de alérgenos por producto.
     *
     * @return array<array> [product_id, product_name, allergen_count]
     */
    public function getAllergenStatistics(): array
    {
        $stmt = $this->getDb()->query('
            SELECT
                p.id,
                p.name,
                COUNT(pa.allergen_id) as allergen_count
            FROM products p
            LEFT JOIN product_allergens pa ON p.id = pa.product_id
            WHERE p.is_active = 1 AND p.product_type = "item"
            GROUP BY p.id
            ORDER BY allergen_count DESC, p.name
        ');

        return $stmt->fetchAll();
    }
}
