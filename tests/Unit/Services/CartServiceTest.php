<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? CartService: validaciones de ID y delgación al repositorio de productos.
 * ¿Qué me quieres demostrar? Que un productId inválido devuelve ok sin modificar carrito, y que un producto no encontrado no lanza excepción.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la guard de productId<=0 en updateItem o cambia la lógica de producto no disponible.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\ProductDTO;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\CartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartService::class)]
final class CartServiceTest extends TestCase
{
    private ProductRepositoryInterface $productRepoStub;
    private ReservationItemRepositoryInterface $itemRepoStub;
    private CartService $service;

    protected function setUp(): void
    {
        $this->productRepoStub = $this->createStub(ProductRepositoryInterface::class);
        $this->itemRepoStub    = $this->createStub(ReservationItemRepositoryInterface::class);
        $this->service         = new CartService($this->productRepoStub, $this->itemRepoStub);
    }

    public function testUpdateItemReturnsOkWhenProductIdIsZero(): void
    {
        $result = $this->service->updateItem(0, 1);

        $this->assertTrue($result->ok);
    }

    public function testUpdateItemReturnsOkWhenChangeIsZero(): void
    {
        $result = $this->service->updateItem(1, 0);

        $this->assertTrue($result->ok);
    }

    public function testUpdateItemReturnsOkWhenProductNotFoundInRepo(): void
    {
        $this->productRepoStub->method('findById')->willReturn(null);

        $result = $this->service->updateItem(1, 1);

        $this->assertTrue($result->ok);
    }

    public function testUpdateItemReturnsOkWhenProductIsInactive(): void
    {
        $this->productRepoStub->method('findById')->willReturn(
            new ProductDTO(
                id: 1,
                name: 'Test',
                slug: 'test',
                description: null,
                price: 0.0,
                category_id: 0,
                category_name: '',
                allergens: [],
                is_active: false,
                image_url: null,
                product_type: 'item',
                min_pax: null,
                max_pax: null,
                duration_minutes: null,
                attributes: null,
                target_cafe_types: null,
                target_animal_types: null,
                stock_quantity: null,
            )
        );

        $result = $this->service->updateItem(1, 1);

        $this->assertTrue($result->ok);
    }

    public function testUpdateItemReturnsOkWhenProductIsNotAnItem(): void
    {
        $this->productRepoStub->method('findById')->willReturn(
            new ProductDTO(
                id: 1,
                name: 'Test',
                slug: 'test',
                description: null,
                price: 0.0,
                category_id: 0,
                category_name: '',
                allergens: [],
                is_active: true,
                image_url: null,
                product_type: 'pass',
                min_pax: null,
                max_pax: null,
                duration_minutes: null,
                attributes: null,
                target_cafe_types: null,
                target_animal_types: null,
                stock_quantity: null,
            )
        );

        $result = $this->service->updateItem(1, 1);

        $this->assertTrue($result->ok);
    }

    public function testAddReturnsOkWhenProductIdIsNegative(): void
    {
        $result = $this->service->add(-1, 2);

        $this->assertTrue($result->ok);
    }

    public function testGetReturnsArrayWithExpectedKeys(): void
    {
        $result = $this->service->get();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('totalQty', $result);
        $this->assertArrayHasKey('totalPrice', $result);
    }

    public function testIsEmptyReturnsTrueForEmptyCart(): void
    {
        $this->assertTrue($this->service->isEmpty());
    }

    public function testGetItemsForReservationReturnsArray(): void
    {
        $result = $this->service->getItemsForReservation();

        $this->assertIsArray($result);
    }

    public function testGetQuantityReturnsZeroForNonExistentProduct(): void
    {
        $qty = $this->service->getQuantity(999);

        $this->assertSame(0, $qty);
    }
}
