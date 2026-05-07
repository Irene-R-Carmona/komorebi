<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ProductService: validación de campos obligatorios al crear/actualizar y delegación al repositorio.
 * ¿Qué me quieres demostrar? Que create/update sin name/slug/category_id lanzan ValidationException, y que las consultas delegan al repositorio.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan los campos obligatorios o cambia la excepción lanzada.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\ProductDTO;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductService;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductService::class)]
final class ProductServiceTest extends TestCase
{
    private ProductRepositoryInterface $repoStub;
    private ProductService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(ProductRepositoryInterface::class);
        $this->service = new ProductService($this->repoStub);
    }

    public function testCreateThrowsValidationExceptionWhenNameMissing(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create(['slug' => 'a', 'category_id' => 1]);
    }

    public function testCreateThrowsValidationExceptionWhenSlugMissing(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'Test', 'category_id' => 1]);
    }

    public function testCreateThrowsValidationExceptionWhenCategoryIdMissing(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'Test', 'slug' => 'test']);
    }

    public function testCreateSucceedsWithRequiredFields(): void
    {
        $this->repoStub->method('create')->willReturn(5);

        $id = $this->service->create(['name' => 'Matcha Latte', 'slug' => 'matcha-latte', 'category_id' => 2]);

        $this->assertSame(5, $id);
    }

    public function testUpdateThrowsValidationExceptionWhenNameMissing(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->update(1, ['slug' => 'test', 'category_id' => 1]);
    }

    public function testUpdateThrowsValidationExceptionWhenSlugMissing(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->update(1, ['name' => 'Test', 'category_id' => 1]);
    }

    public function testGetAllReturnsDelegatedArray(): void
    {
        $expected = [['id' => 1, 'name' => 'Product']];
        $this->repoStub->method('findAllWithCategoryName')->willReturn($expected);

        $result = $this->service->getAll();

        $this->assertSame($expected, $result);
    }

    public function testGetByIdReturnsDelegatedData(): void
    {
        $expected = new ProductDTO(
            id: 1,
            name: 'Product',
            slug: 'product',
            description: null,
            price: 0.0,
            category_id: 0,
            category_name: '',
            allergens: [],
            is_active: true,
            image_url: null,
            product_type: 'item',
            min_pax: null,
            max_pax: null,
            duration_minutes: null,
            attributes: null,
            target_cafe_types: null,
            target_animal_types: null,
            stock_quantity: null,
        );
        $this->repoStub->method('findById')->willReturn($expected);

        $result = $this->service->getById(1);

        $this->assertSame($expected, $result);
    }

    public function testSearchReturnsDelegatedArray(): void
    {
        $expected = [['id' => 1, 'name' => 'Product']];
        $this->repoStub->method('search')->willReturn($expected);

        $result = $this->service->search('prod');

        $this->assertSame($expected, $result);
    }

    public function testCreateThrowsValidationExceptionWhenCategoryIdMissingOnUpdate(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->update(1, ['name' => 'Test', 'slug' => 'test']);
    }

    public function testCreateThrowsValidationExceptionWhenProductTypeInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'X', 'slug' => 'x', 'category_id' => 1, 'product_type' => 'invalid_type']);
    }

    public function testCreateThrowsValidationExceptionWhenStationInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'X', 'slug' => 'x', 'category_id' => 1, 'station' => 'invalid_station']);
    }

    public function testGetAllPaginatedReturnsDelegatedArray(): void
    {
        $expected = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => 20, 'totalPages' => 0];
        $this->repoStub->method('findFiltered')->willReturn($expected);

        $result = $this->service->getAllPaginated(1, 20, []);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testUpdateThrowsValidationExceptionWhenProductTypeInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->update(1, ['name' => 'X', 'slug' => 'x', 'category_id' => 1, 'product_type' => 'bad_type']);
    }

    public function testUpdateThrowsValidationExceptionWhenStationInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->update(1, ['name' => 'X', 'slug' => 'x', 'category_id' => 1, 'station' => 'bad_station']);
    }

    public function testGetByCategoryReturnsDelegatedArray(): void
    {
        $expected = [['id' => 1, 'name' => 'Matcha Latte', 'category_id' => 2]];
        $this->repoStub->method('findByCategoryId')->willReturn($expected);

        $result = $this->service->getByCategory(2);

        $this->assertSame($expected, $result);
    }

    public function testGetAllWithAllergensReturnsDelegatedArray(): void
    {
        $expected = [['id' => 2, 'name' => 'Onigiri', 'allergens' => ['gluten']]];
        $this->repoStub->method('getAllWithAllergens')->willReturn($expected);

        $result = $this->service->getAllWithAllergens();

        $this->assertSame($expected, $result);
    }

    public function testGetAllWithAllergensWithCategoryIdFilters(): void
    {
        $expected = [['id' => 3, 'name' => 'Mochi', 'allergens' => []]];
        $this->repoStub->method('getAllWithAllergens')->willReturn($expected);

        $result = $this->service->getAllWithAllergens(5);

        $this->assertSame($expected, $result);
    }

    public function testGetAllergensByProductReturnsDelegatedArray(): void
    {
        $expected = [['id' => 1, 'name' => 'Gluten'], ['id' => 3, 'name' => 'Lácteos']];
        $this->repoStub->method('getAllergens')->willReturn($expected);

        $result = $this->service->getAllergensByProduct(7);

        $this->assertSame($expected, $result);
    }

    public function testGetWithoutAllergensReturnsDelegatedArrayWhenIdsProvided(): void
    {
        $expected = [['id' => 5, 'name' => 'Té verde sin gluten']];
        $this->repoStub->method('findWithoutAllergensByCategory')->willReturn($expected);

        $result = $this->service->getWithoutAllergens([1, 2], null);

        $this->assertSame($expected, $result);
    }

    public function testDeleteReturnsFalseWhenRepoReturnsFalse(): void
    {
        $this->repoStub->method('softDelete')->willReturn(false);

        $result = $this->service->delete(99);

        $this->assertFalse($result);
    }

    public function testToggleActiveReturnsFalseWhenRepoReturnsFalse(): void
    {
        $this->repoStub->method('toggleAvailability')->willReturn(false);

        $result = $this->service->toggleActive(99);

        $this->assertFalse($result);
    }

    public function testSyncAllergensReturnsFalseWhenRepoReturnsFalse(): void
    {
        $this->repoStub->method('syncAllergens')->willReturn(false);

        $result = $this->service->syncAllergens(1, [1, 2, 3]);

        $this->assertFalse($result);
    }

    public function testCreateThrowsDatabaseExceptionOnPDOException(): void
    {
        $pdo = new PDOException('Connection failed');
        $this->repoStub->method('create')->willThrowException($pdo);

        $this->expectException(\App\Exceptions\DatabaseException::class);

        $this->service->create(['name' => 'X', 'slug' => 'x', 'category_id' => 1]);
    }

    public function testUpdateReturnsFalseWhenRepoReturnsFalse(): void
    {
        $this->repoStub->method('update')->willReturn(false);

        $result = $this->service->update(1, ['name' => 'X', 'slug' => 'x', 'category_id' => 1]);

        $this->assertFalse($result);
    }

    public function testUpdateThrowsDatabaseExceptionOnPDOException(): void
    {
        $this->repoStub->method('update')->willThrowException(new PDOException('fail'));

        $this->expectException(\App\Exceptions\DatabaseException::class);

        $this->service->update(1, ['name' => 'X', 'slug' => 'x', 'category_id' => 1]);
    }

    public function testDeleteThrowsDatabaseExceptionOnPDOException(): void
    {
        $this->repoStub->method('softDelete')->willThrowException(new PDOException('fail'));

        $this->expectException(\App\Exceptions\DatabaseException::class);

        $this->service->delete(5);
    }

    public function testToggleActiveReturnsTrueWhenRepoReturnsTrue(): void
    {
        $this->repoStub->method('toggleAvailability')->willReturn(true);

        $result = $this->service->toggleActive(5);

        $this->assertTrue($result);
    }

    public function testToggleActiveReturnsFalseOnPDOException(): void
    {
        $this->repoStub->method('toggleAvailability')->willThrowException(new PDOException('fail'));

        $result = $this->service->toggleActive(5);

        $this->assertFalse($result);
    }

    public function testGetAllReturnsCachedDataWhenCacheHit(): void
    {
        $cached = [['id' => 42, 'name' => 'Cached Product']];
        \App\Core\Cache::set('products:all', $cached);

        try {
            $result = $this->service->getAll();
            $this->assertSame($cached, $result);
        } finally {
            \App\Core\Cache::delete('products:all');
        }
    }
}
