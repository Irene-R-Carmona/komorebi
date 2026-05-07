<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
/**
 * Tests de Integración de MenuService
 *
 * Valida operaciones con MySQL 8.4 real usando transacciones para aislamiento.
 * Estos tests NO usan mocks - ejecutan queries reales contra la BD.
 */

namespace Tests\Integration;

use App\Repositories\MenuRepository;
use App\Services\MenuService;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Support\BaseIntegrationTest;

#[CoversNothing]
final class MenuIntegrationTest extends BaseIntegrationTest
{
    private MenuService $service;

    // IDs únicos para tests
    private const TEST_CATEGORY_ID = 99990;
    private const TEST_PRODUCT_ID = 99991;
    private const TEST_PASS_ID = 99992;
    private const TEST_ALLERGEN_ID = 9991;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->service = new MenuService(new MenuRepository(self::$db));
    }

    /**
     * Seed de datos de prueba
     */
    private function seedTestData(): void
    {
        // Limpiar datos previos si existen
        self::$db->exec('DELETE FROM product_allergens WHERE product_id = ' . self::TEST_PRODUCT_ID);
        self::$db->exec('DELETE FROM products WHERE id IN (' . self::TEST_PRODUCT_ID . ', ' . self::TEST_PASS_ID . ')');
        self::$db->exec('DELETE FROM menu_categories WHERE id = ' . self::TEST_CATEGORY_ID);
        self::$db->exec('DELETE FROM allergens WHERE id = ' . self::TEST_ALLERGEN_ID);

        // Categoría de prueba
        self::$db->exec('
            INSERT INTO menu_categories (id, name, slug, display_order)
            VALUES (
                ' . self::TEST_CATEGORY_ID . ",
                'Integration Test Category',
                'integration-test-category',
                99
            )
        ");

        // Alérgeno de prueba
        self::$db->exec('
            INSERT INTO allergens (id, code, name, japanese_name, severity)
            VALUES (
                ' . self::TEST_ALLERGEN_ID . ",
                'TESTALLR',
                'Test Allergen',
                'テストアレルゲン',
                'medium'
            )
        ");

        // Producto tipo 'item' de prueba
        self::$db->exec('
            INSERT INTO products (
                id, category_id, product_type, name, japanese_name,
                description, price, is_active
            )
            VALUES (
                ' . self::TEST_PRODUCT_ID . ',
                ' . self::TEST_CATEGORY_ID . ",
                'item',
                'Test Product',
                'テスト商品',
                'Product for integration testing',
                1000,
                1
            )
        ");

        // Asociar alérgeno al producto
        self::$db->exec('
            INSERT INTO product_allergens (product_id, allergen_id)
            VALUES (' . self::TEST_PRODUCT_ID . ', ' . self::TEST_ALLERGEN_ID . ')
        ');

        // Pase tipo 'pass'
        self::$db->exec('
            INSERT INTO products (
                id, category_id, product_type, name, description,
                price, is_active, pass_duration_minutes, min_pax, max_pax,
                target_cafe_types
            )
            VALUES (
                ' . self::TEST_PASS_ID . ',
                ' . self::TEST_CATEGORY_ID . ",
                'pass',
                'Test Pass 1H',
                'Pass for integration testing',
                1500,
                1,
                60,
                1,
                4,
                '[\"lounge\"]'
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────
    // Integration Tests
    // ─────────────────────────────────────────────────────────────

    public function testGetCategoriesReturnsDataFromDatabase(): void
    {
        // ACT: Obtener categorías sin filtro de experiencias
        $result = $this->service->getCategories(false);

        // ASSERT: Debe retornar array
        $this->assertIsArray($result);

        // ASSERT: Debe incluir nuestra categoría de test
        $categorySlugs = \array_column($result, 'slug');
        $this->assertContains('integration-test-category', $categorySlugs);

        // ASSERT: Cada categoría tiene estructura correcta
        foreach ($result as $category) {
            $this->assertArrayHasKey('id', $category);
            $this->assertArrayHasKey('name', $category);
            $this->assertArrayHasKey('slug', $category);
            $this->assertArrayHasKey('display_order', $category);
        }
    }

    public function testGetProductsByCategoryIncludesAllergensFromDatabase(): void
    {
        // ACT: Obtener productos sin filtro de alérgenos
        $result = $this->service->getProductsByCategory();

        // ASSERT: Debe retornar productos agrupados por categoría
        $this->assertIsArray($result);

        // ASSERT: Debe incluir nuestra categoría de test
        $this->assertArrayHasKey(self::TEST_CATEGORY_ID, $result);

        // ASSERT: Debe tener nuestro producto
        $testCategoryProducts = $result[self::TEST_CATEGORY_ID];
        $this->assertCount(1, $testCategoryProducts);

        $product = $testCategoryProducts[0];
        $this->assertSame('Test Product', $product['name']);
        $this->assertSame('テスト商品', $product['japanese_name']);
        $this->assertSame(1000, (int) $product['price']);

        // ASSERT: Debe tener allergens_list con nuestro alérgeno
        $this->assertArrayHasKey('allergens_list', $product);
        $this->assertIsArray($product['allergens_list']);
        $this->assertCount(1, $product['allergens_list']);

        $allergen = $product['allergens_list'][0];
        $this->assertSame(self::TEST_ALLERGEN_ID, (int) $allergen['id']);
        $this->assertSame('Test Allergen', $allergen['name']);
    }

    public function testGetProductsByCategoryFiltersAllergensByDatabaseQuery(): void
    {
        // ACT: Obtener productos excluyendo nuestro alérgeno de test
        $result = $this->service->getProductsByCategory([self::TEST_ALLERGEN_ID]);

        // ASSERT: Debe retornar array
        $this->assertIsArray($result);

        // ASSERT: No debe incluir nuestro producto (tiene el alérgeno excluido)
        if (isset($result[self::TEST_CATEGORY_ID])) {
            $testProducts = \array_filter(
                $result[self::TEST_CATEGORY_ID],
                fn ($p) => (int) $p['id'] === self::TEST_PRODUCT_ID
            );
            $this->assertEmpty($testProducts, 'Producto con alérgeno excluido no debe aparecer');
        }
    }

    public function testGetPassesForCafeFiltersPassesByTargetsInDatabase(): void
    {
        // ACT: Obtener pases para café tipo 'lounge'
        $result = $this->service->getPassesForCafe('lounge', 'cat');

        // ASSERT: Debe retornar array
        $this->assertIsArray($result);

        // ASSERT: Debe incluir nuestro pase (target_cafe_types = ["lounge"])
        $passIds = \array_column($result, 'id');
        $this->assertContains(self::TEST_PASS_ID, \array_map('intval', $passIds));

        // Verificar estructura del pase
        $testPass = null;
        foreach ($result as $pass) {
            if ((int) $pass['id'] === self::TEST_PASS_ID) {
                $testPass = $pass;
                break;
            }
        }

        $this->assertNotNull($testPass, 'Test pass debe estar en resultados');
        $this->assertSame('Test Pass 1H', $testPass['name']);
        $this->assertSame(1500, (int) $testPass['price']);
        $this->assertSame(60, (int) $testPass['duration_minutes']);
    }

    public function testGetPassesForCafeExcludesIncompatiblePasses(): void
    {
        // ACT: Obtener pases para café tipo 'farm' (nuestro pase es 'lounge')
        $result = $this->service->getPassesForCafe('farm', 'rabbit');

        // ASSERT: Debe retornar array
        $this->assertIsArray($result);

        // ASSERT: NO debe incluir nuestro pase (target_cafe_types = ["lounge"], no compatible con 'farm')
        $passIds = \array_column($result, 'id');
        $this->assertNotContains(
            self::TEST_PASS_ID,
            \array_map('intval', $passIds),
            'Pase lounge no debe aparecer en búsqueda de farm'
        );
    }

    public function testGetAllergensReturnsDataFromDatabase(): void
    {
        // ACT: Obtener todos los alérgenos
        $result = $this->service->getAllergens();

        // ASSERT: Debe retornar array
        $this->assertIsArray($result);

        // ASSERT: Debe incluir nuestro alérgeno de test
        $allergenNames = \array_column($result, 'name');
        $this->assertContains('Test Allergen', $allergenNames);

        // Verificar estructura correcta
        foreach ($result as $allergen) {
            $this->assertArrayHasKey('id', $allergen);
            $this->assertArrayHasKey('name', $allergen);
            $this->assertArrayHasKey('name_jp', $allergen);
            $this->assertArrayHasKey('icon', $allergen);
            $this->assertArrayHasKey('icon_color', $allergen);
            $this->assertArrayHasKey('severity', $allergen);
        }
    }
}
