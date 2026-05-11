<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * Seeder de inclusiones de pases (pass_inclusions)
 *
 * Define qué categorías de menú (Bebidas, Comida, Repostería) están
 * incluidas en cada tipo de pase, con cantidad por pax y precio máximo elegible.
 * Los IDs se resuelven por slug/nombre — nunca hardcodeados.
 */
final class PassInclusionsSeeder
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('PassInclusionsSeeder: starting');

        $passIds = $this->resolvePassIds();
        $categoryIds = $this->resolveCategoryIds();

        if (\count($passIds) === 0 || \count($categoryIds) === 0) {
            Logger::warning('PassInclusionsSeeder: passes or categories not found, skipping');
            return;
        }

        // Filas para pases con slug (pases estándar 64-73):
        // [pass_slug, category_key, qty_per_pax, max_unit_price_cents|null]
        $rows = [
            ['pass-extendido',    'bebidas',       1, 500],
            ['pass-familiar',     'bebidas',       1, 500],
            ['pass-grupo-grande', 'bebidas',       1, 500],
            ['pass-foto-pro',     'bebidas',       1, 500],
            ['pass-zen',          'bebidas',       1, 550],
            ['pass-granja',       'bebidas',       1, 550],
            ['pass-granja',       'animal_snacks', 1, 500],
            ['pass-dia-completo', 'bebidas',       1, null],
            ['pass-dia-completo', 'postres',       1, 700],
            ['pass-vip-private',  'bebidas',       1, null],
            ['pass-vip-private',  'comida',        1, null],
            ['pass-vip-private',  'postres',       1, null],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO pass_inclusions (pass_product_id, category_id, quantity_per_pax, max_unit_price)
             VALUES (:pass_id, :category_id, :qty, :max_price)
             ON DUPLICATE KEY UPDATE
               quantity_per_pax = VALUES(quantity_per_pax),
               max_unit_price   = VALUES(max_unit_price)'
        );

        $inserted = 0;

        // Seedear pases con slug (pases estándar)
        foreach ($rows as [$passSlug, $categoryKey, $qty, $maxPrice]) {
            if (!isset($passIds[$passSlug]) || !isset($categoryIds[$categoryKey])) {
                Logger::warning('PassInclusionsSeeder: missing ID', [
                    'pass_slug' => $passSlug,
                    'category_key' => $categoryKey,
                ]);
                continue;
            }
            $stmt->execute([
                'pass_id'     => $passIds[$passSlug],
                'category_id' => $categoryIds[$categoryKey],
                'qty'         => $qty,
                'max_price'   => $maxPrice,
            ]);
            $inserted++;
        }

        // Seedear pases sin slug (pases temáticos 43-58) por atributos JSON
        $attrRows = $this->resolveUnsluggedPassRows($categoryIds);
        foreach ($attrRows as $row) {
            $stmt->execute($row);
            $inserted++;
        }

        Logger::info('PassInclusionsSeeder: completed', ['rows_processed' => $inserted]);
    }

    /**
     * Resuelve filas de inclusiones para pases sin slug (pases temáticos 43-58).
     * Consulta la columna JSON `attributes` para determinar qué incluye cada pase.
     *
     * @param array<string, int> $categoryIds
     * @return array<int, array{pass_id: int, category_id: int, qty: int, max_price: int|null}>
     */
    private function resolveUnsluggedPassRows(array $categoryIds): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, attributes FROM products
             WHERE product_type = 'pass'
               AND (slug IS NULL OR slug = '')
               AND attributes IS NOT NULL"
        );
        $stmt->execute();
        $passes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($passes as $pass) {
            $attrs = \json_decode((string) $pass['attributes'], true);
            if (!\is_array($attrs)) {
                continue;
            }

            $passId = (int) $pass['id'];

            if (!empty($attrs['includes_drink']) && isset($categoryIds['bebidas'])) {
                $rows[] = [
                    'pass_id'     => $passId,
                    'category_id' => $categoryIds['bebidas'],
                    'qty'         => 1,
                    'max_price'   => null,
                ];
            }

            if (!empty($attrs['includes_dessert']) && isset($categoryIds['postres'])) {
                $rows[] = [
                    'pass_id'     => $passId,
                    'category_id' => $categoryIds['postres'],
                    'qty'         => 1,
                    'max_price'   => 700,
                ];
            }

            if (!empty($attrs['includes_feed']) && isset($categoryIds['animal_snacks'])) {
                $rows[] = [
                    'pass_id'     => $passId,
                    'category_id' => $categoryIds['animal_snacks'],
                    'qty'         => 1,
                    'max_price'   => null,
                ];
            }
        }

        return $rows;
    }

    /**
     * Resuelve los IDs de los pases por slug.
     *
     * @return array<string, int>
     */
    private function resolvePassIds(): array
    {
        $slugs = [
            'pass-extendido',
            'pass-familiar',
            'pass-grupo-grande',
            'pass-foto-pro',
            'pass-zen',
            'pass-granja',
            'pass-dia-completo',
            'pass-vip-private',
        ];

        $placeholders = \implode(',', \array_fill(0, \count($slugs), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, slug FROM products WHERE product_type = 'pass' AND slug IN ({$placeholders})"
        );
        $stmt->execute($slugs);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['slug']] = (int) $row['id'];
        }

        return $result;
    }

    /**
     * Resuelve los IDs de las categorías de menú por slug normalizado.
     *
     * @return array<string, int>
     */
    private function resolveCategoryIds(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, slug FROM menu_categories WHERE slug IN ('bebidas', 'comida', 'postres', 'animal_snacks')"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['slug']] = (int) $row['id'];
        }

        return $result;
    }
}
