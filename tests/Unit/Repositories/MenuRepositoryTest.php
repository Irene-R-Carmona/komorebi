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
use PHPUnit\Framework\TestCase;

/**
 * Tests para MenuRepository
 */
final class MenuRepositoryTest extends TestCase
{
    private MenuRepository $repository;

    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
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
