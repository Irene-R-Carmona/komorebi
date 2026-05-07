<?php

/**
 * ¿Qué prueba aquí? ProductRepository — todos los métodos de acceso a datos de productos.
 * ¿Qué me quieres demostrar? Que cada método ejecuta la consulta correcta y mapea los datos esperados.
 * ¿Qué va a fallar si se cambia el código? Cambios en SQL, mapeo de DTOs, lógica de stock,
 *   paginación de findFiltered, decodificación de JSON en findAllAdmin, o normalización de alérgenos.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\ProductDTO;
use App\Repositories\ProductRepository;
use PDO;
use PDOStatement;

final class ProductRepositoryTest extends RepositoryTestCase
{
    // ------------------------------------------------------------------
    // findById
    // ------------------------------------------------------------------

    public function testFindByIdReturnsDtoWhenRowFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::productRow());
        $repo = new ProductRepository($pdo);

        $result = $repo->findById(1);

        $this->assertInstanceOf(ProductDTO::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('Café Matcha', $result->name);
    }

    public function testFindByIdReturnsNullWhenNoRow(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertNull($repo->findById(99));
    }

    // ------------------------------------------------------------------
    // findWithRecipe
    // ------------------------------------------------------------------

    public function testFindWithRecipeReturnsArrayWhenFound(): void
    {
        $row = RowFactory::productRow(['station' => 'bar']);
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new ProductRepository($pdo);

        $result = $repo->findWithRecipe(1);

        $this->assertIsArray($result);
        $this->assertSame('bar', $result['station']);
    }

    public function testFindWithRecipeReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertNull($repo->findWithRecipe(99));
    }

    // ------------------------------------------------------------------
    // findByCafeId
    // ------------------------------------------------------------------

    public function testFindByCafeIdReturnsProducts(): void
    {
        $rows = [
            RowFactory::productRow(),
            RowFactory::productRow(['id' => 2, 'name' => 'Té Verde']),
        ];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findByCafeId(1);

        $this->assertCount(2, $result);
        $this->assertSame('Café Matcha', $result[0]['name']);
    }

    // ------------------------------------------------------------------
    // findByCategoryId
    // ------------------------------------------------------------------

    public function testFindByCategoryIdWithoutCafeId(): void
    {
        $rows = [RowFactory::productRow(['category_id' => 3])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findByCategoryId(3);

        $this->assertCount(1, $result);
    }

    public function testFindByCategoryIdWithCafeId(): void
    {
        $rows = [RowFactory::productRow()];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findByCategoryId(2, 1);

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // findByProductType
    // ------------------------------------------------------------------

    public function testFindByProductTypeWithoutCafeId(): void
    {
        $rows = [RowFactory::productRow(['product_type' => 'pass'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findByProductType('pass', null);

        $this->assertCount(1, $result);
        $this->assertSame('pass', $result[0]['product_type']);
    }

    public function testFindByProductTypeWithCafeId(): void
    {
        $rows = [RowFactory::productRow(['product_type' => 'item'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findByProductType('item', 1);

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // findPasses / findItems
    // ------------------------------------------------------------------

    public function testFindPassesDelegatesToFindByProductType(): void
    {
        $rows = [RowFactory::productRow(['product_type' => 'pass'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findPasses();

        $this->assertCount(1, $result);
        $this->assertSame('pass', $result[0]['product_type']);
    }

    public function testFindItemsDelegatesToFindByProductType(): void
    {
        $rows = [RowFactory::productRow(['product_type' => 'item'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findItems(1);

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // findAvailablePasses (usa query() + decodePassJsonColumns)
    // ------------------------------------------------------------------

    public function testFindAvailablePassesDecodesJsonColumns(): void
    {
        $passRow = RowFactory::productRow([
            'product_type' => 'pass',
            'target_cafe_types' => '["cat","dog"]',
            'target_animal_types' => '["cat"]',
            'attributes' => '{"sessions":5}',
        ]);
        $pdo = $this->makePdo(fetchAllReturn: [$passRow]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findAvailablePasses();

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['target_cafe_types']);
        $this->assertSame(['cat', 'dog'], $result[0]['target_cafe_types']);
        $this->assertIsArray($result[0]['attributes']);
        $this->assertSame(['sessions' => 5], $result[0]['attributes']);
    }

    public function testFindAvailablePassesLeavesNullColumnsAsNull(): void
    {
        $passRow = RowFactory::productRow([
            'product_type' => 'pass',
            'target_cafe_types' => null,
            'target_animal_types' => null,
            'attributes' => null,
        ]);
        $pdo = $this->makePdo(fetchAllReturn: [$passRow]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findAvailablePasses();

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['target_cafe_types']);
    }

    public function testFindAvailablePassesReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ProductRepository($pdo);

        $this->assertSame([], $repo->findAvailablePasses());
    }

    // ------------------------------------------------------------------
    // existsAndActivePass
    // ------------------------------------------------------------------

    public function testExistsAndActivePassReturnsTrueWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['id' => 1, 'product_type' => 'pass']);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->existsAndActivePass(1));
    }

    public function testExistsAndActivePassReturnsFalseWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->existsAndActivePass(99));
    }

    // ------------------------------------------------------------------
    // findItemsByIds
    // ------------------------------------------------------------------

    public function testFindItemsByIdsReturnsEmptyArrayForEmptyInput(): void
    {
        $pdo = $this->createStub(PDO::class);
        $repo = new ProductRepository($pdo);

        $this->assertSame([], $repo->findItemsByIds([]));
    }

    public function testFindItemsByIdsReturnsRows(): void
    {
        $rows = [
            ['id' => 10, 'name' => 'Matcha Latte', 'price' => 650],
            ['id' => 20, 'name' => 'Croissant', 'price' => 350],
        ];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findItemsByIds([10, 20]);

        $this->assertCount(2, $result);
    }

    // ------------------------------------------------------------------
    // findByIds
    // ------------------------------------------------------------------

    public function testFindByIdsReturnsEmptyArrayForEmptyInput(): void
    {
        $pdo = $this->createStub(PDO::class);
        $repo = new ProductRepository($pdo);

        $this->assertSame([], $repo->findByIds([]));
    }

    public function testFindByIdsReturnsRowsKeyedById(): void
    {
        $row1 = RowFactory::productRow(['id' => 1, 'name' => 'Matcha']);
        $row2 = RowFactory::productRow(['id' => 2, 'name' => 'Té verde']);

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturnOnConsecutiveCalls($row1, $row2, false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ProductRepository($pdo);
        $result = $repo->findByIds([1, 2]);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame('Matcha', $result[1]['name']);
    }

    // ------------------------------------------------------------------
    // hasStock
    // ------------------------------------------------------------------

    public function testHasStockReturnsFalseWhenProductNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->hasStock(99, 1));
    }

    public function testHasStockReturnsTrueForUnlimitedStock(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['stock_quantity' => null, 'is_active' => 1, 'deleted_at' => null]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->hasStock(1, 100));
    }

    public function testHasStockReturnsTrueWhenSufficientStock(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['stock_quantity' => 10, 'is_active' => 1, 'deleted_at' => null]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->hasStock(1, 5));
    }

    public function testHasStockReturnsFalseWhenInsufficientStock(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['stock_quantity' => 2, 'is_active' => 1, 'deleted_at' => null]);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->hasStock(1, 5));
    }

    public function testHasStockReturnsFalseWhenProductInactive(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['stock_quantity' => 10, 'is_active' => 0, 'deleted_at' => null]);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->hasStock(1, 1));
    }

    // ------------------------------------------------------------------
    // decrementStock
    // ------------------------------------------------------------------

    public function testDecrementStockReturnsFalseWhenProductNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->decrementStock(99));
    }

    public function testDecrementStockReturnsTrueForUnlimitedStock(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['stock_quantity' => null]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->decrementStock(1));
    }

    public function testDecrementStockReturnsFalseWhenInsufficientStock(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['stock_quantity' => 1]);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->decrementStock(1, 5));
    }

    public function testDecrementStockSucceedsWithSufficientStock(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => ['stock_quantity' => 10]],
            ['rowCount' => 1],
        ]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->decrementStock(1, 3));
    }

    // ------------------------------------------------------------------
    // incrementStock
    // ------------------------------------------------------------------

    public function testIncrementStockReturnsFalseWhenProductNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->incrementStock(99));
    }

    public function testIncrementStockReturnsTrueForUnlimitedStock(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['stock_quantity' => null]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->incrementStock(1));
    }

    public function testIncrementStockSucceedsForControlledStock(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => ['stock_quantity' => 5]],
            ['rowCount' => 1],
        ]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->incrementStock(1, 2));
    }

    // ------------------------------------------------------------------
    // toggleAvailability
    // ------------------------------------------------------------------

    public function testToggleAvailabilityReturnsFalseWhenProductNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->toggleAvailability(99));
    }

    public function testToggleAvailabilityTogglesActiveProduct(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => RowFactory::productRow(['is_active' => 1])],
            ['rowCount' => 1],
        ]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->toggleAvailability(1));
    }

    public function testToggleAvailabilityTogglesInactiveProduct(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => RowFactory::productRow(['is_active' => 0])],
            ['rowCount' => 1],
        ]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->toggleAvailability(1));
    }

    // ------------------------------------------------------------------
    // getAllergens
    // ------------------------------------------------------------------

    public function testGetAllergensReturnsAllergenRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Gluten'],
            ['id' => 2, 'name' => 'Leche'],
        ];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->getAllergens(1);

        $this->assertCount(2, $result);
        $this->assertSame('Gluten', $result[0]['name']);
    }

    public function testGetAllergensReturnsEmptyWhenNone(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ProductRepository($pdo);

        $this->assertSame([], $repo->getAllergens(1));
    }

    // ------------------------------------------------------------------
    // findWithoutAllergens
    // ------------------------------------------------------------------

    public function testFindWithoutAllergensWithoutCafeId(): void
    {
        $rows = [RowFactory::productRow()];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findWithoutAllergens([1, 2]);

        $this->assertCount(1, $result);
    }

    public function testFindWithoutAllergensWithCafeId(): void
    {
        $rows = [RowFactory::productRow()];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findWithoutAllergens([1], 2);

        $this->assertCount(1, $result);
    }

    public function testFindWithoutAllergensWithEmptyListReturnsAll(): void
    {
        $rows = [RowFactory::productRow(), RowFactory::productRow(['id' => 2])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findWithoutAllergens([]);

        $this->assertCount(2, $result);
    }

    // ------------------------------------------------------------------
    // findFiltered (2 prepare: COUNT + data)
    // ------------------------------------------------------------------

    public function testFindFilteredReturnsPaginatedStructure(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '5'],
            ['fetchAll' => [RowFactory::productRow(), RowFactory::productRow(['id' => 2])]],
        ]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findFiltered([], 1, 20);

        $this->assertSame(5, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['perPage']);
        $this->assertCount(2, $result['data']);
    }

    public function testFindFilteredWithCategoryAndTypeFilters(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '2'],
            ['fetchAll' => [RowFactory::productRow(['product_type' => 'pass'])]],
        ]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findFiltered(
            ['product_type' => 'pass', 'is_active' => 1, 'category_id' => 2],
            1,
            10
        );

        $this->assertSame(2, $result['total']);
    }

    public function testFindFilteredWithSearchFilter(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '1'],
            ['fetchAll' => [RowFactory::productRow(['name' => 'Matcha Latte'])]],
        ]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findFiltered(['search' => 'matcha'], 1, 10);

        $this->assertSame(1, $result['total']);
    }

    // ------------------------------------------------------------------
    // findAllAdmin (delega a findFiltered y decodifica JSON)
    // ------------------------------------------------------------------

    public function testFindAllAdminDecodesJsonFields(): void
    {
        $row = RowFactory::productRow([
            'attributes' => '{"sessions":5}',
            'target_cafe_types' => '["cat","dog"]',
            'target_animal_types' => '["cat"]',
        ]);
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '1'],
            ['fetchAll' => [$row]],
        ]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findAllAdmin();

        $this->assertIsArray($result['data'][0]['attributes']);
        $this->assertSame(['sessions' => 5], $result['data'][0]['attributes']);
        $this->assertIsArray($result['data'][0]['target_cafe_types']);
        $this->assertSame(['cat', 'dog'], $result['data'][0]['target_cafe_types']);
    }

    public function testFindAllAdminSetsNullForMissingJsonFields(): void
    {
        $row = RowFactory::productRow([
            'attributes' => null,
            'target_cafe_types' => null,
            'target_animal_types' => null,
        ]);
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '1'],
            ['fetchAll' => [$row]],
        ]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findAllAdmin();

        $this->assertNull($result['data'][0]['attributes']);
    }

    // ------------------------------------------------------------------
    // findAllActive (usa query())
    // ------------------------------------------------------------------

    public function testFindAllActiveReturnsActiveProducts(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::productRow()]);
        $repo = new ProductRepository($pdo);

        $result = $repo->findAllActive();

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // findByCategory
    // ------------------------------------------------------------------

    public function testFindByCategoryReturnsMatchingProducts(): void
    {
        $rows = [RowFactory::productRow(['category_name' => 'Bebidas'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findByCategory('bebidas');

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // getAllWithAllergens
    // ------------------------------------------------------------------

    public function testGetAllWithAllergensNormalizesGroupConcatAllergens(): void
    {
        $row = RowFactory::productRow([
            'allergen_ids' => '1,2',
            'allergen_names' => 'Gluten,Leche',
            'allergen_codes' => 'GLU,LAC',
            'allergen_severities' => 'high,medium',
        ]);
        $pdo = $this->makePdo(fetchAllReturn: [$row]);
        $repo = new ProductRepository($pdo);

        $result = $repo->getAllWithAllergens();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('allergens_list', $result[0]);
        $this->assertCount(2, $result[0]['allergens_list']);
        $this->assertSame('Gluten', $result[0]['allergens_list'][0]['name']);
        $this->assertSame(1, $result[0]['allergens_list'][0]['id']);
        $this->assertArrayNotHasKey('allergen_ids', $result[0]);
    }

    public function testGetAllWithAllergensReturnsEmptyListWhenNoAllergens(): void
    {
        $row = RowFactory::productRow([
            'allergen_ids' => null,
            'allergen_names' => null,
            'allergen_codes' => null,
            'allergen_severities' => null,
        ]);
        $pdo = $this->makePdo(fetchAllReturn: [$row]);
        $repo = new ProductRepository($pdo);

        $result = $repo->getAllWithAllergens();

        $this->assertSame([], $result[0]['allergens_list']);
    }

    public function testGetAllWithAllergensWithCategoryFilter(): void
    {
        $row = RowFactory::productRow([
            'allergen_ids' => null,
            'allergen_names' => null,
            'allergen_codes' => null,
            'allergen_severities' => null,
        ]);
        $pdo = $this->makePdo(fetchAllReturn: [$row]);
        $repo = new ProductRepository($pdo);

        $result = $repo->getAllWithAllergens(categoryId: 2);

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // getCategories (usa query())
    // ------------------------------------------------------------------

    public function testGetCategoriesReturnsAllCategories(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Bebidas', 'display_order' => 1],
            ['id' => 2, 'name' => 'Comida', 'display_order' => 2],
        ];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->getCategories();

        $this->assertCount(2, $result);
        $this->assertSame('Bebidas', $result[0]['name']);
    }

    // ------------------------------------------------------------------
    // findAllWithCategoryName (usa query())
    // ------------------------------------------------------------------

    public function testFindAllWithCategoryNameReturnsRowsWithCategory(): void
    {
        $rows = [RowFactory::productRow(['category_name' => 'Bebidas'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findAllWithCategoryName();

        $this->assertCount(1, $result);
        $this->assertSame('Bebidas', $result[0]['category_name']);
    }

    // ------------------------------------------------------------------
    // search
    // ------------------------------------------------------------------

    public function testSearchReturnsMatchingProducts(): void
    {
        $rows = [RowFactory::productRow(['name' => 'Matcha Latte'])];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->search('matcha');

        $this->assertCount(1, $result);
    }

    public function testSearchReturnsEmptyWhenNoMatch(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ProductRepository($pdo);

        $this->assertSame([], $repo->search('xyz-no-existe'));
    }

    // ------------------------------------------------------------------
    // syncAllergens
    // ------------------------------------------------------------------

    public function testSyncAllergensWithEmptyListOnlyDeletes(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['rowCount' => 1],
        ]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->syncAllergens(1, []));
    }

    public function testSyncAllergensWithAllergenIdsDeletesThenInserts(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['rowCount' => 1],
            ['rowCount' => 1],
        ]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->syncAllergens(1, [3, 7]));
    }

    // ------------------------------------------------------------------
    // findWithoutAllergensByCategory
    // ------------------------------------------------------------------

    public function testFindWithoutAllergensByCategoryReturnsRows(): void
    {
        $rows = [RowFactory::productRow()];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findWithoutAllergensByCategory([1, 2]);

        $this->assertCount(1, $result);
    }

    public function testFindWithoutAllergensByCategoryWithCategoryFilter(): void
    {
        $rows = [RowFactory::productRow()];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findWithoutAllergensByCategory([1], 3);

        $this->assertCount(1, $result);
    }

    public function testFindWithoutAllergensByCategoryDelegatesToAllWhenEmpty(): void
    {
        $rows = [RowFactory::productRow()];
        $pdo = $this->makePdo(fetchAllReturn: $rows);
        $repo = new ProductRepository($pdo);

        $result = $repo->findWithoutAllergensByCategory([]);

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // getAdminStats (usa query() + fetch)
    // ------------------------------------------------------------------

    public function testGetAdminStatsReturnsExpectedFields(): void
    {
        $statsRow = [
            'total_products' => 10,
            'active_products' => 8,
            'inactive_products' => 2,
            'category_count' => 3,
            'with_allergens' => 4,
            'with_stock' => 6,
        ];
        $pdo = $this->makePdo(fetchReturn: $statsRow);
        $repo = new ProductRepository($pdo);

        $result = $repo->getAdminStats();

        $this->assertSame(10, $result['total_products']);
        $this->assertSame(8, $result['active_products']);
        $this->assertSame(3, $result['category_count']);
    }

    public function testGetAdminStatsReturnsDefaultsWhenRowIsFalse(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $result = $repo->getAdminStats();

        $this->assertSame(0, $result['total_products']);
        $this->assertSame(0, $result['with_allergens']);
    }

    // ------------------------------------------------------------------
    // AbstractRepository CRUD vía ProductRepository
    // ------------------------------------------------------------------

    public function testCreateReturnsLastInsertId(): void
    {
        $pdo = $this->makePdo(lastInsertId: '42');
        $repo = new ProductRepository($pdo);

        $id = $repo->create(['name' => 'Nuevo producto', 'price' => 5.00, 'category_id' => 1]);

        $this->assertSame(42, $id);
    }

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ProductRepository($pdo);

        $result = $repo->update(1, ['name' => 'Producto renombrado']);

        $this->assertTrue($result);
    }

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->delete(1));
    }

    public function testExistsReturnsTrueWhenRowFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['id' => 1]);
        $repo = new ProductRepository($pdo);

        $this->assertTrue($repo->exists(1));
    }

    public function testExistsReturnsFalseWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ProductRepository($pdo);

        $this->assertFalse($repo->exists(99));
    }
}
