<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\PassInclusionRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio de inclusiones de pases.
 *
 * Gestiona qué categorías de productos (Bebidas, Comida, Repostería)
 * están incluidas en cada tipo de pase, con cantidad por pax y precio máximo.
 */
final class PassInclusionRepository extends AbstractRepository implements PassInclusionRepositoryInterface
{
    #[Override]
    protected function getTable(): string
    {
        return 'pass_inclusions';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'pass_product_id', 'category_id', 'quantity_per_pax', 'max_unit_price'];
    }

    /**
     * Devuelve las inclusiones de un pase con datos de categoría.
     *
     * @return array<int, array{
     *     id: int,
     *     pass_product_id: int,
     *     category_id: int,
     *     quantity_per_pax: int,
     *     max_unit_price: int|null,
     *     category_name: string,
     *     category_slug: string
     * }>
     */
    #[Override]
    public function findByPassId(int $passProductId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT pi.id,
                    pi.pass_product_id,
                    pi.category_id,
                    pi.quantity_per_pax,
                    pi.max_unit_price,
                    mc.name  AS category_name,
                    mc.slug  AS category_slug
             FROM pass_inclusions pi
             INNER JOIN menu_categories mc ON mc.id = pi.category_id
             WHERE pi.pass_product_id = :pass_id
             ORDER BY mc.display_order'
        );
        $stmt->execute(['pass_id' => $passProductId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function findByPassIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
        $stmt = $this->getDb()->prepare(
            "SELECT pi.id,
                    pi.pass_product_id,
                    pi.category_id,
                    pi.quantity_per_pax,
                    pi.max_unit_price,
                    mc.name  AS category_name,
                    mc.slug  AS category_slug
             FROM pass_inclusions pi
             INNER JOIN menu_categories mc ON mc.id = pi.category_id
             WHERE pi.pass_product_id IN ({$placeholders})
             ORDER BY mc.display_order"
        );
        $stmt->execute($ids);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['pass_product_id']][] = $row;
        }

        return $grouped;
    }
}
