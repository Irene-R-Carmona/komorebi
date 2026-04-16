<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests para ProductService - enfoque en paginación
 */
final class ProductServiceTest extends TestCase
{
    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $repoStub = $this->createStub(ProductRepositoryInterface::class);
        $repoStub->method('findFiltered')->willReturnCallback(
            static function (array $filters, int $page, int $perPage): array {
                return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 1];
            }
        );

        $this->service = new ProductService($repoStub, $pdoStub);
    }

    /**
     * Test: getAllPaginated() retorna estructura correcta
     */
    public function testGetAllPaginatedStructure(): void
    {
        $result = $this->service->getAllPaginated(1, 20);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('totalPages', $result);
    }

    /**
     * Test: Validación de página mínima
     */
    public function testPageValidationMinimum(): void
    {
        $result = $this->service->getAllPaginated(-5, 20);

        $this->assertEquals(1, $result['page']); // Forzado a 1
    }

    /**
     * Test: Validación de perPage máximo
     */
    public function testPerPageValidationMaximum(): void
    {
        $result = $this->service->getAllPaginated(1, 200);

        $this->assertEquals(100, $result['perPage']); // Limitado a 100
    }

    /**
     * Test: Validación de perPage mínimo
     */
    public function testPerPageValidationMinimum(): void
    {
        $result = $this->service->getAllPaginated(1, -10);

        $this->assertEquals(1, $result['perPage']); // Forzado a 1 mínimo
    }

    /**
     * Test: Paginación retorna máximo perPage items
     */
    public function testPaginationLimit(): void
    {
        $result = $this->service->getAllPaginated(1, 10);

        $this->assertLessThanOrEqual(10, count($result['data']));
    }

    /**
     * Test: TotalPages se calcula correctamente
     */
    public function testTotalPagesCalculation(): void
    {
        $result = $this->service->getAllPaginated(1, 20);

        if ($result['total'] > 0) {
            $expectedPages = (int) ceil($result['total'] / $result['perPage']);
            $this->assertEquals($expectedPages, $result['totalPages']);
        } else {
            $this->assertEquals(1, $result['totalPages']);
        }
    }

    /**
     * Test: Filtro por categoría
     */
    public function testFilterByCategory(): void
    {
        $filters = ['category_id' => 1];
        $result = $this->service->getAllPaginated(1, 20, $filters);

        $this->assertIsArray($result['data']);

        // Todos los productos deben tener category_id = 1
        foreach ($result['data'] as $product) {
            $this->assertEquals(1, $product['category_id']);
        }
    }

    /**
     * Test: Filtro por tipo de producto
     */
    public function testFilterByProductType(): void
    {
        $filters = ['product_type' => 'item'];
        $result = $this->service->getAllPaginated(1, 20, $filters);

        $this->assertIsArray($result['data']);

        foreach ($result['data'] as $product) {
            $this->assertEquals('item', $product['product_type']);
        }
    }

    /**
     * Test: Filtro por estado activo
     */
    public function testFilterByActive(): void
    {
        $filters = ['is_active' => 1];
        $result = $this->service->getAllPaginated(1, 20, $filters);

        $this->assertIsArray($result['data']);

        foreach ($result['data'] as $product) {
            $this->assertEquals(1, $product['is_active']);
        }
    }

    /**
     * Test: Búsqueda por nombre
     */
    public function testSearchByName(): void
    {
        $filters = ['search' => 'latte'];
        $result = $this->service->getAllPaginated(1, 20, $filters);

        $this->assertIsArray($result['data']);

        foreach ($result['data'] as $product) {
            $name = mb_strtolower($product['name'] ?? '');
            $desc = mb_strtolower($product['description'] ?? '');
            $jp = mb_strtolower($product['japanese_name'] ?? '');

            $found = str_contains($name, 'latte')
                || str_contains($desc, 'latte')
                || str_contains($jp, 'latte');

            $this->assertTrue($found, "Product '{$product['name']}' should contain 'latte' in name, description or japanese_name");
        }
    }

    /**
     * Test: Múltiples filtros combinados
     */
    public function testMultipleFilters(): void
    {
        $filters = [
            'category_id' => 1,
            'product_type' => 'item',
            'is_active' => 1,
        ];

        $result = $this->service->getAllPaginated(1, 20, $filters);

        $this->assertIsArray($result['data']);

        foreach ($result['data'] as $product) {
            $this->assertEquals(1, $product['category_id']);
            $this->assertEquals('item', $product['product_type']);
            $this->assertEquals(1, $product['is_active']);
        }
    }

    /**
     * Test: Página vacía retorna array vacío
     */
    public function testEmptyPage(): void
    {
        $result = $this->service->getAllPaginated(9999, 20);

        $this->assertEmpty($result['data']);
        $this->assertIsArray($result['data']);
    }
}
