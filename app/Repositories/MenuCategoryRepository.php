<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\DTO\MenuCategoryDTO;
use App\Domain\Mappers\MenuCategoryMapper;
use App\Repositories\Contracts\MenuCategoryRepositoryInterface;
use PDO;

final class MenuCategoryRepository implements MenuCategoryRepositoryInterface
{
    private PDO $db;

    public function __construct(private readonly MenuCategoryMapper $mapper, ?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * @return array<int, MenuCategoryDTO>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, slug, display_order
             FROM menu_categories
             ORDER BY display_order, name'
        );

        return \array_map([$this->mapper, 'toDTO'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug, display_order
             FROM menu_categories
             WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAllWithProductCount(): array
    {
        $stmt = $this->db->query(
            'SELECT c.id, c.name, c.slug, c.display_order,
                    COUNT(p.id) as product_count,
                    SUM(IF(p.is_active = 1, 1, 0)) as available_count
             FROM menu_categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.display_order, c.name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
