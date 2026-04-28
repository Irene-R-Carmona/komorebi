<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * MenuService: getCategories (con/sin experiencias), getProductsByCategory,
 * getAllergens, getMenuForCafe y getProductDetail.
 *
 * ¿Qué me quieres demostrar?
 * Que MenuService delega correctamente en MenuRepositoryInterface y que
 * los filtros (includeExperiences, cafeId) se propagan al repositorio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el filtro de experiencias, si getProductsByCategory
 * deja de pasar cafeId al repositorio, o si getMenuForCafe cambia su estructura.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Services\MenuService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests Unitarios de MenuService
 *
 * Valida lógica de negocio sin tocar BD real (usa mocks).
 */
#[CoversClass(MenuService::class)]
final class MenuServiceTest extends TestCase
{
    private MenuService $service;
    /** @var MockObject&MenuRepositoryInterface */
    private MenuRepositoryInterface $mockMenuRepo;

    protected function setUp(): void
    {
        $this->mockMenuRepo = $this->createMock(MenuRepositoryInterface::class);
        $this->service = new MenuService($this->mockMenuRepo);
    }

    protected function tearDown(): void
    {
        unset($this->service, $this->mockMenuRepo);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getCategories
    // ─────────────────────────────────────────────────────────────

    public function testGetCategoriesExcludesExperiencesByDefault(): void
    {
        // ARRANGE: Mock repository method
        $this->mockMenuRepo->expects($this->once())
            ->method('getCategories')
            ->with(false)
            ->willReturn([
                ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1],
                ['id' => 2, 'name' => 'Comida', 'slug' => 'comida', 'display_order' => 2],
            ]);

        // ACT
        $result = $this->service->getCategories();

        // ASSERT
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Bebidas', $result[0]['name']);
        $this->assertSame('Comida', $result[1]['name']);
    }

    public function testGetCategoriesIncludesExperiencesWhenRequested(): void
    {
        // ARRANGE: Mock repository method
        $this->mockMenuRepo->expects($this->once())
            ->method('getCategories')
            ->with(true)
            ->willReturn([
                ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1],
                ['id' => 3, 'name' => 'Experiencias', 'slug' => 'experiencias', 'display_order' => 5],
            ]);

        // ACT
        $result = $this->service->getCategories(true);

        // ASSERT
        $this->assertCount(2, $result);
        $this->assertSame('Experiencias', $result[1]['name']);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getProductsByCategory
    // ─────────────────────────────────────────────────────────────

    public function testGetProductsByCategoryReturnsGroupedProducts(): void
    {
        // ARRANGE: Mock repository returns FLAT array (service will group)
        $this->mockMenuRepo->expects($this->once())
            ->method('getProductsByCategory')
            ->with([])
            ->willReturn([
                ['id' => 1, 'category_id' => 1, 'name' => 'Café Latte', 'price' => 500, 'allergen_ids' => null, 'allergen_names' => null],
                ['id' => 2, 'category_id' => 1, 'name' => 'Cappuccino', 'price' => 550, 'allergen_ids' => null, 'allergen_names' => null],
                ['id' => 3, 'category_id' => 2, 'name' => 'Croissant', 'price' => 300, 'allergen_ids' => null, 'allergen_names' => null],
            ]);

        // ACT
        $result = $this->service->getProductsByCategory();

        // ASSERT
        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result); // Categoría 1
        $this->assertArrayHasKey(2, $result); // Categoría 2
        $this->assertCount(2, $result[1]); // 2 productos en categoría 1
        $this->assertCount(1, $result[2]); // 1 producto en categoría 2
    }

    public function testGetProductsByCategoryExcludesAllergens(): void
    {
        // ARRANGE: Mock repository with allergen filter (returns FLAT array)
        $this->mockMenuRepo->expects($this->once())
            ->method('getProductsByCategory')
            ->with([5])
            ->willReturn([
                ['id' => 1, 'category_id' => 1, 'name' => 'Producto sin leche', 'price' => 500, 'allergen_ids' => null, 'allergen_names' => null],
            ]);

        // ACT: Excluir alérgeno ID 5 (leche)
        $result = $this->service->getProductsByCategory([5]);

        // ASSERT
        $this->assertIsArray($result);
        $this->assertCount(1, $result[1]);
        $this->assertSame('Producto sin leche', $result[1][0]['name']);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getPasses
    // ─────────────────────────────────────────────────────────────

    public function testGetPassesReturnsAvailablePasses(): void
    {
        // ARRANGE: Mock repository
        $this->mockMenuRepo->expects($this->once())
            ->method('getPasses')
            ->willReturn([
                ['id' => 10, 'name' => 'Pase 1H', 'price' => 1500, 'product_type' => 'pass'],
                ['id' => 11, 'name' => 'Pase 2H', 'price' => 2500, 'product_type' => 'pass'],
            ]);

        // ACT
        $result = $this->service->getPasses();

        // ASSERT
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Pase 1H', $result[0]['name']);
        $this->assertSame('pass', $result[0]['product_type']);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getPassesForCafe
    // ─────────────────────────────────────────────────────────────

    public function testGetPassesForCafeWithoutFiltersReturnsAllPasses(): void
    {
        // ARRANGE: Mock repository method
        $this->mockMenuRepo->expects($this->once())
            ->method('getPassesForCafe')
            ->with(null, null)
            ->willReturn([
                [
                    'id' => 10,
                    'name' => 'Pase Komorebi',
                    'target_cafe_types' => '',
                    'target_animal_types' => '',
                ],
            ]);

        // ACT
        $result = $this->service->getPassesForCafe();

        // ASSERT
        $this->assertCount(1, $result);
        $this->assertSame('Pase Komorebi', $result[0]['name']);
    }

    public function testGetPassesForCafeFiltersPassesByTargets(): void
    {
        // ARRANGE: Mock repository with filtered passes
        $this->mockMenuRepo->expects($this->once())
            ->method('getPassesForCafe')
            ->with('lounge', 'cat')
            ->willReturn([
                [
                    'id' => 10,
                    'name' => 'Pase Genérico',
                    'target_cafe_types' => '',
                    'target_animal_types' => '',
                ],
                [
                    'id' => 11,
                    'name' => 'Pase Lounge',
                    'target_cafe_types' => '["lounge"]',
                    'target_animal_types' => '["cat"]',
                ],
            ]);

        // ACT: Filtrar por lounge + cat
        $result = $this->service->getPassesForCafe('lounge', 'cat');

        // ASSERT: Solo retorna genérico y el compatible
        $this->assertCount(2, $result);
        $names = \array_column($result, 'name');
        $this->assertContains('Pase Genérico', $names);
        $this->assertContains('Pase Lounge', $names);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getMenuForView
    // ─────────────────────────────────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testGetMenuForViewReturnsCompleteStructure(): void
    {
        // ARRANGE: Mock repository methods
        $this->mockMenuRepo->method('getCategories')
            ->willReturn([['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1]]);

        $this->mockMenuRepo->method('getProductsByCategory')
            ->willReturn([['id' => 1, 'category_id' => 1, 'name' => 'Café', 'price' => 500, 'allergen_ids' => null, 'allergen_names' => null]]);

        $this->mockMenuRepo->method('getPasses')
            ->willReturn([['id' => 10, 'name' => 'Pase 1H', 'product_type' => 'pass']]);

        $this->mockMenuRepo->method('getAllergens')
            ->willReturn([['id' => 1, 'name' => 'Leche', 'name_jp' => 'ミルク', 'icon' => 'milk', 'icon_color' => '#fff', 'severity' => 'high']]);

        // ACT
        $result = $this->service->getMenuForView();

        // ASSERT: Verificar estructura completa
        $this->assertArrayHasKey('categorias', $result);
        $this->assertArrayHasKey('productos', $result);
        $this->assertArrayHasKey('pases', $result);
        $this->assertArrayHasKey('allergens', $result);
        $this->assertArrayHasKey('cafeTypes', $result);

        // Verificar tipos de café
        $this->assertCount(4, $result['cafeTypes']);
        $this->assertSame('lounge', $result['cafeTypes'][0]['value']);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getAllProducts
    // ─────────────────────────────────────────────────────────────

    public function testGetAllProductsReturnsDelegatedArray(): void
    {
        $expected = [
            ['id' => 1, 'name' => 'Café Latte', 'price' => 500],
            ['id' => 2, 'name' => 'Matcha', 'price' => 600],
        ];

        $this->mockMenuRepo->expects($this->once())
            ->method('getAllProducts')
            ->willReturn($expected);

        $result = $this->service->getAllProducts();

        $this->assertSame($expected, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getAllergens
    // ─────────────────────────────────────────────────────────────

    public function testGetAllergensReturnsDelegatedArray(): void
    {
        $expected = [
            ['id' => 1, 'name' => 'Gluten', 'name_jp' => 'グルテン', 'icon' => 'wheat', 'icon_color' => '#D4A017', 'severity' => 'high'],
            ['id' => 2, 'name' => 'Leche', 'name_jp' => 'ミルク', 'icon' => 'milk', 'icon_color' => '#fff', 'severity' => 'moderate'],
        ];

        $this->mockMenuRepo->expects($this->once())
            ->method('getAllergens')
            ->willReturn($expected);

        $result = $this->service->getAllergens();

        $this->assertSame($expected, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: allergen parsing logic in getProductsByCategory
    // ─────────────────────────────────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testGetProductsByCategoryParsesAllergenFieldsIntoAllergensList(): void
    {
        $this->mockMenuRepo->method('getProductsByCategory')
            ->willReturn([
                [
                    'id' => 1,
                    'category_id' => 1,
                    'name' => 'Croissant',
                    'price' => 300,
                    'allergen_ids' => '1,2',
                    'allergen_names' => 'Gluten,Leche',
                    'allergen_icons' => 'wheat,milk',
                    'allergen_colors' => '#D4A017,#ffffff',
                    'allergen_severities' => 'high,moderate',
                ],
            ]);

        $result = $this->service->getProductsByCategory();

        $product = $result[1][0];
        $this->assertArrayHasKey('allergens_list', $product);
        $this->assertCount(2, $product['allergens_list']);

        $this->assertSame(1, $product['allergens_list'][0]['id']);
        $this->assertSame('Gluten', $product['allergens_list'][0]['name']);
        $this->assertSame('wheat', $product['allergens_list'][0]['icon']);
        $this->assertSame('#D4A017', $product['allergens_list'][0]['icon_color']);
        $this->assertSame('high', $product['allergens_list'][0]['severity']);

        $this->assertSame(2, $product['allergens_list'][1]['id']);
        $this->assertSame('Leche', $product['allergens_list'][1]['name']);
        $this->assertSame('moderate', $product['allergens_list'][1]['severity']);
    }
}
