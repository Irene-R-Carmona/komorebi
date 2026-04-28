<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\MenuRepositoryInterface;
use PDO;

/**
 * Repositorio de Menú
 *
 * Encapsula el acceso a datos relacionados con categorías de menú,
 * productos, pases y alérgenos.
 */
final class MenuRepository implements MenuRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtener todas las categorías de menú ordenadas
     */
    public function getCategories(bool $includeExperiences = false): array
    {
        $sql = 'SELECT id, name, slug, display_order FROM menu_categories';

        if (!$includeExperiences) {
            $sql .= " WHERE slug != 'experiencias'";
        }

        $sql .= ' ORDER BY display_order, id';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener productos agrupados por categoría con alérgenos
     */
    public function getProductsByCategory(array $excludeAllergenIds = []): array
    {
        $excludeAllergenIds = \array_values(\array_filter(\array_map('intval', $excludeAllergenIds), static fn ($v) => $v > 0));

        $sql = "
            SELECT
                p.id,
                p.name,
                p.japanese_name,
                p.description,
                p.price,
                p.category_id,
                p.product_type,
                p.is_active,
                p.image_url,
                p.target_cafe_types,
                p.target_animal_types,
                mc.name AS category_name,
                mc.slug AS category_slug,
                GROUP_CONCAT(DISTINCT a.id) AS allergen_ids,
                GROUP_CONCAT(DISTINCT a.name) AS allergen_names,
                GROUP_CONCAT(DISTINCT a.icon_class) AS allergen_icons,
                GROUP_CONCAT(DISTINCT a.icon_color) AS allergen_colors,
                GROUP_CONCAT(DISTINCT a.severity) AS allergen_severities
            FROM products p
            INNER JOIN menu_categories mc ON p.category_id = mc.id
            LEFT JOIN product_allergens pa ON p.id = pa.product_id
            LEFT JOIN allergens a ON pa.allergen_id = a.id
            WHERE p.is_active = 1
              AND p.product_type = 'item'
        ";

        if (!empty($excludeAllergenIds)) {
            $placeholders = \implode(',', \array_fill(0, \count($excludeAllergenIds), '?'));
            $sql .= " AND p.id NOT IN (
                SELECT product_id
                FROM product_allergens
                WHERE allergen_id IN ($placeholders)
            )";
        }

        $sql .= ' GROUP BY p.id, p.name, p.japanese_name, p.description, p.price, p.category_id, p.product_type, p.is_active, p.image_url, p.target_cafe_types, p.target_animal_types, mc.name, mc.slug
                  ORDER BY mc.display_order, p.name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($excludeAllergenIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener todos los productos activos
     */
    public function getAllProducts(): array
    {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.price,
                p.category_id,
                p.product_type,
                p.is_active,
                p.image_url,
                mc.name AS category_name,
                mc.slug AS category_slug
            FROM products p
            INNER JOIN menu_categories mc ON p.category_id = mc.id
            WHERE p.is_active = 1
              AND p.product_type = 'item'
            ORDER BY mc.display_order, p.name
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener todos los pases disponibles
     */
    public function getPasses(): array
    {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.price,
                p.product_type,
                p.is_active,
                p.image_url,
                p.pass_duration_minutes,
                p.max_pax,
                p.min_pax
            FROM products p
            WHERE p.is_active = 1
              AND p.product_type = 'pass'
            ORDER BY p.price ASC
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener pases filtrados por tipo de café y animal
     */
    public function getPassesForCafe(?string $cafeCategory = null, ?string $animalType = null): array
    {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.description,
                p.price,
                p.product_type,
                p.is_active,
                p.image_url,
                p.pass_duration_minutes AS duration_minutes,
                p.max_pax,
                p.min_pax,
                p.target_cafe_types,
                p.target_animal_types
            FROM products p
            WHERE p.is_active = 1
              AND p.product_type = 'pass'
        ";

        $params = [];

        // Filtrar por targets si hay filtros (columnas JSON separadas)
        $conditions = [];

        if ($cafeCategory !== null) {
            // Solo incluir pases sin restricciones O que incluyan explícitamente este cafe_type
            $conditions[] = "(p.target_cafe_types IS NULL OR p.target_cafe_types = '' OR p.target_cafe_types = '[]' OR JSON_CONTAINS(p.target_cafe_types, :cafe_category))";
            $params['cafe_category'] = \json_encode($cafeCategory);
        }

        if ($animalType !== null) {
            // Solo incluir pases sin restricciones O que incluyan explícitamente este animal_type
            $conditions[] = "(p.target_animal_types IS NULL OR p.target_animal_types = '' OR p.target_animal_types = '[]' OR JSON_CONTAINS(p.target_animal_types, :animal_type))";
            $params['animal_type'] = \json_encode($animalType);
        }

        if (!empty($conditions)) {
            $sql .= ' AND ' . \implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.price ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener todos los alérgenos
     */
    public function getAllergens(): array
    {
        $sql = 'SELECT id, name, code, japanese_name AS name_jp, icon_class AS icon, icon_color, severity FROM allergens ORDER BY name';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener alérgenos de un producto específico
     */
    public function getAllergensByProduct(int $productId): array
    {
        $sql = '
            SELECT DISTINCT
                a.id,
                a.name,
                a.code,
                a.japanese_name AS name_jp,
                a.icon_class AS icon,
                a.icon_color,
                a.severity
            FROM allergens a
            INNER JOIN product_allergens pa ON a.id = pa.allergen_id
            WHERE pa.product_id = :product_id
            ORDER BY a.name
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
