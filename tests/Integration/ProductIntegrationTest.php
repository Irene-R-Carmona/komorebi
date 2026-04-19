<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
/**
 * Tests de Integración de ProductService
 *
 * Valida operaciones con MySQL 8.4 real usando transacciones para aislamiento.
 * Estos tests NO usan mocks - ejecutan queries reales contra la BD.
 */

namespace Tests\Integration;

use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Override;
use Tests\Support\BaseIntegrationTest;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ProductIntegrationTest extends BaseIntegrationTest
{
    private ProductService $service;

    // IDs únicos para tests
    private const TEST_CATEGORY_ID = 88880;
    private const TEST_PRODUCT_ID_BASE = 88881;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $productRepo = new ProductRepository();
        $this->service = new ProductService($productRepo);
    }

    /**
     * Seed de datos de prueba
     */
    private function seedTestData(): void
    {
        // Limpiar datos previos
        self::$db->exec('DELETE FROM products WHERE id >= ' . self::TEST_PRODUCT_ID_BASE);
        self::$db->exec('DELETE FROM menu_categories WHERE id = ' . self::TEST_CATEGORY_ID);

        // Categoría de prueba
        self::$db->exec('
            INSERT INTO menu_categories (id, name, slug)
            VALUES (
                ' . self::TEST_CATEGORY_ID . ",
                'Test Category Integration',
                'test-category-integration'
            )
        ");

        // Producto activo tipo 'item'
        self::$db->exec('
            INSERT INTO products (
                id, category_id, product_type, name, slug, price,
                station, is_active
            )
            VALUES (
                ' . self::TEST_PRODUCT_ID_BASE . ',
                ' . self::TEST_CATEGORY_ID . ",
                'item',
                'Test Product Active',
                'test-product-active',
                1500,
                'assembly',
                1
            )
        ");

        // Producto inactivo tipo 'pass'
        self::$db->exec('
            INSERT INTO products (
                id, category_id, product_type, name, slug, price,
                station, duration_minutes, is_active
            )
            VALUES (
                ' . (self::TEST_PRODUCT_ID_BASE + 1) . ',
                ' . self::TEST_CATEGORY_ID . ",
                'pass',
                'Test Pass Inactive',
                'test-pass-inactive',
                3000,
                'assembly',
                90,
                0
            )
        ");

        // Producto con texto para búsqueda
        self::$db->exec('
            INSERT INTO products (
                id, category_id, product_type, name, slug, description,
                price, station, is_active
            )
            VALUES (
                ' . (self::TEST_PRODUCT_ID_BASE + 2) . ',
                ' . self::TEST_CATEGORY_ID . ",
                'item',
                'Matcha Latte Premium',
                'matcha-latte-premium',
                'Bebida de té verde con leche',
                2500,
                'bar',
                1
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────
    // Integration Tests
    // ─────────────────────────────────────────────────────────────

    public function testGetAllPaginatedReturnsDataFromDatabase(): void
    {
        // ACT: Obtener productos paginados
        $result = $this->service->getAllPaginated(1, 20);

        // ASSERT: Estructura correcta
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('totalPages', $result);

        // Debe incluir nuestros 3 productos de test
        $this->assertGreaterThanOrEqual(3, $result['total']);
        $this->assertIsArray($result['data']);
    }

    public function testGetAllPaginatedFiltersProductsByCategory(): void
    {
        // ACT: Filtrar por categoría de test
        $result = $this->service->getAllPaginated(1, 20, [
            'category_id' => self::TEST_CATEGORY_ID,
        ]);

        // ASSERT: Solo debe retornar productos de nuestra categoría
        $this->assertCount(3, $result['data'], 'Debe retornar exactamente 3 productos de la categoría de test');

        foreach ($result['data'] as $product) {
            $this->assertEquals(self::TEST_CATEGORY_ID, $product['category_id']);
        }
    }

    public function testGetAllPaginatedFiltersProductsByActiveStatus(): void
    {
        // ACT: Filtrar solo productos activos de nuestra categoría
        $result = $this->service->getAllPaginated(1, 20, [
            'category_id' => self::TEST_CATEGORY_ID,
            'is_active' => 1,
        ]);

        // ASSERT: Solo debe retornar productos activos (2 de 3)
        $this->assertCount(2, $result['data'], 'Debe retornar 2 productos activos');

        foreach ($result['data'] as $product) {
            $this->assertEquals(1, $product['is_active']);
        }
    }

    public function testGetAllPaginatedSearchesByProductName(): void
    {
        // ACT: Buscar por texto "Matcha"
        $result = $this->service->getAllPaginated(1, 20, [
            'category_id' => self::TEST_CATEGORY_ID,
            'search' => 'Matcha',
        ]);

        // ASSERT: Debe retornar solo el producto con "Matcha" en el nombre
        $this->assertGreaterThanOrEqual(1, \count($result['data']), 'Debe encontrar al menos 1 producto con "Matcha"');

        $found = false;
        foreach ($result['data'] as $product) {
            if (\stripos($product['name'], 'Matcha') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Debe encontrar producto con "Matcha" en el nombre');
    }
}
