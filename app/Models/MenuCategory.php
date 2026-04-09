<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Models\Traits\ValidatesData;
use PDO;

/**
 * Modelo MenuCategory
 *
 * Categorías del menú (bebidas, comida, postres, etc.)
 */
final class MenuCategory
{
    use ValidatesData;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene todas las categorías ordenadas.
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, slug, display_order
             FROM menu_categories
             ORDER BY display_order , name '
        );

        return $stmt->fetchAll();
    }

    /**
     * Busca una categoría por slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug, display_order
             FROM menu_categories
             WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Obtiene categorías con conteo de productos.
     */
    public function findAllWithProductCount(): array
    {
        $stmt = $this->db->query(
            'SELECT c.id, c.name, c.slug, c.display_order,
                    COUNT(p.id) as product_count,
                    SUM(IF(p.is_active = 1, 1, 0)) as available_count
             FROM menu_categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.display_order , c.name '
        );

        return $stmt->fetchAll();
    }
}
