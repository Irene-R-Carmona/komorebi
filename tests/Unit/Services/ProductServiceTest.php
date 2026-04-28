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
        $this->service  = new ProductService($this->repoStub);
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
}
