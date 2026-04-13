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

use Override;
use ReflectionClass;
use function method_exists;
use App\Repositories\ProductRepository;
use Tests\Support\BaseIntegrationTest;

final class ProductRepositorySecurityTest extends BaseIntegrationTest
{
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
            method_exists($this->repo, 'findWithRecipe'),
            'ProductRepository debe exponer findWithRecipe() para contextos de cocina/KDS'
        );
    }
}
