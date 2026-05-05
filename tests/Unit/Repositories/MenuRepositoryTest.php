<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Repositories;

use App\Repositories\MenuRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests para MenuRepository
 *
 * ¿Qué pruebas aquí? Que el repositorio de menú devuelve los datos correctos y que la SQL
 * incluye todas las columnas necesarias, incluidas target_cafe_types y target_animal_types.
 * ¿Qué me quieres demostrar? Que los productos traen los filtros de tipo de café y animal.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina target_cafe_types o
 * target_animal_types del SELECT, el test falla de inmediato.
 */
#[CoversClass(MenuRepository::class)]
final class MenuRepositoryTest extends TestCase
{
    private MenuRepository $repository;

    /** @var \PHPUnit\Framework\MockObject\Stub&PDO */
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = $this->createStub(PDO::class);
        $this->repository = new MenuRepository($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->db);
    }

    public function testRepositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(MenuRepository::class, $this->repository);
    }

    public function testGetCategoriesReturnsArray(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1],
            ['id' => 2, 'name' => 'Postres', 'slug' => 'postres', 'display_order' => 2],
        ]);

        $this->db->method('query')->willReturn($stmt);

        $result = $this->repository->getCategories();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Bebidas', $result[0]['name']);
    }

    public function testGetProductsByCategoryReturnsArray(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'name' => 'Café Latte',
                'description' => 'Café con leche',
                'price' => 3.50,
                'category_id' => 1,
                'category_name' => 'Bebidas',
                'allergen_ids' => '1,2',
                'allergen_names' => 'Leche,Gluten',
            ],
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->getProductsByCategory();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('name', $result[0]);
    }

    public function testGetProductsByCategoryIncludesTargetTypeColumns(): void
    {
        $capturedSql = null;
        $db = $this->createStub(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;

                return $stmt;
            });

        $repo = new MenuRepository($db);
        $repo->getProductsByCategory();

        $this->assertIsString($capturedSql, 'PDO::prepare() debe haber sido llamado con una SQL');
        $this->assertStringContainsString(
            'target_cafe_types',
            $capturedSql,
            'La query de productos debe seleccionar p.target_cafe_types para filtrado por tipo de café'
        );
        $this->assertStringContainsString(
            'target_animal_types',
            $capturedSql,
            'La query de productos debe seleccionar p.target_animal_types para filtrado por tipo de animal'
        );
    }

    public function testGetAllProductsReturnsArray(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Producto 1', 'price' => 5.00],
            ['id' => 2, 'name' => 'Producto 2', 'price' => 7.50],
        ]);

        $this->db->method('query')->willReturn($stmt);

        $result = $this->repository->getAllProducts();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetAllergensReturnsArray(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Leche', 'code' => 'milk'],
            ['id' => 2, 'name' => 'Gluten', 'code' => 'gluten'],
        ]);

        $this->db->method('query')->willReturn($stmt);

        $result = $this->repository->getAllergens();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Leche', $result[0]['name']);
    }
}
