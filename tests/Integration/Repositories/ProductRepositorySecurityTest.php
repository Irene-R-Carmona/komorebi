<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de seguridad del ProductRepository: valida que getSelectFields() jamás expone
 * campos de receta/cocina (recipe_steps, station, critical_check) en consultas estándar.
 *
 * ¿Qué me quieres demostrar?
 * Que getSelectFields() no contiene campos del KDS y que findWithRecipe() sí los contiene.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si alguien añade recipe_steps o station a getSelectFields(), o elimina findWithRecipe().
 */

namespace Tests\Integration\Repositories;

use App\Repositories\ProductRepository;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionClass;
use Tests\Support\BaseIntegrationTest;

#[CoversNothing]
final class ProductRepositorySecurityTest extends BaseIntegrationTest
{
    private const int TEST_PRODUCT_ID = 79002;
    private const int TEST_CATEGORY_ID = 1;

    private ProductRepository $repo;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ProductRepository(self::$db);
    }

    public function testGetSelectFieldsExcludesKitchenOperationalFields(): void
    {
        $reflection = new ReflectionClass($this->repo);
        $method = $reflection->getMethod('getSelectFields');
        $fields = $method->invoke($this->repo);

        $this->assertIsArray($fields);
        $this->assertNotContains('recipe_steps', $fields);
        $this->assertNotContains('ingredients_list', $fields);
        $this->assertNotContains('station', $fields);
        $this->assertNotContains('critical_check', $fields);
    }

    public function testFindWithRecipeMethodExists(): void
    {
        $this->assertTrue(
            \method_exists($this->repo, 'findWithRecipe'), // @phpstan-ignore function.alreadyNarrowedType
            'ProductRepository debe exponer findWithRecipe() para contextos de cocina/KDS'
        );
    }

    public function testFindWithRecipeReturnsKitchenFields(): void
    {
        $this->seedTestProduct();

        $result = $this->repo->findWithRecipe(self::TEST_PRODUCT_ID);

        $this->assertIsArray($result, 'findWithRecipe() debe retornar un array para un ID existente');

        // Campos de cocina/KDS que DEBEN estar presentes
        $this->assertArrayHasKey('station', $result);
        $this->assertArrayHasKey('recipe_steps', $result);
        $this->assertArrayHasKey('ingredients_list', $result);
        $this->assertArrayHasKey('critical_check', $result);

        // Verificar valores sembrados
        $this->assertSame('bar', $result['station']);
        $this->assertStringContainsString('Tamizar matcha', $result['recipe_steps']);
        $this->assertSame('Temperatura agua: 80°C ±5°C (no hervir)', $result['critical_check']);
        // ingredients_list puede volver como JSON string o array según el driver
        $this->assertNotEmpty($result['ingredients_list']);
    }

    private function seedTestProduct(): void
    {
        self::$db->exec('SET FOREIGN_KEY_CHECKS = 0');
        self::$db->exec('
            INSERT INTO products (
                id, category_id, product_type, name, japanese_name, slug, description,
                price, station, prep_time, recipe_steps, ingredients_list, critical_check,
                is_active, sort_order, created_at, updated_at
            ) VALUES (
                ' . self::TEST_PRODUCT_ID . ',
                ' . self::TEST_CATEGORY_ID . ',
                "item",
                "Matcha Latte Test",
                "抹茶ラテ テスト",
                "matcha-latte-test-79002",
                "Versión de prueba para test de seguridad",
                750.00,
                "bar",
                5,
                "1. Tamizar matcha\n2. Añadir agua caliente\n3. Batir\n4. Añadir leche vaporizada",
                \'[{"nombre":"matcha","gramos":3},{"nombre":"leche","ml":200}]\',
                "Temperatura agua: 80°C ±5°C (no hervir)",
                1, 99, NOW(), NOW()
            )
        ');
        self::$db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
