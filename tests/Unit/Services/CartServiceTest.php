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

    public function testUpdateItemAddsProductToCartWhenValidActiveItem(): void
    {
        $dto = new ProductDTO(
            id: 1,
            name: 'Matcha',
            slug: 'matcha',
            description: null,
            price: 5.0,
            category_id: 1,
            category_name: 'Bebidas',
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
        $this->productRepoStub->method('findById')->willReturn($dto);
        $this->productRepoStub->method('findByIds')->willReturn([]);

        $result = $this->service->updateItem(1, 1);

        $this->assertTrue($result->ok);
    }

    public function testUpdateItemRemovesItemWhenNewQtyIsNegative(): void
    {
        $dto = new ProductDTO(
            id: 2,
            name: 'Chai',
            slug: 'chai',
            description: null,
            price: 3.5,
            category_id: 1,
            category_name: 'Bebidas',
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
        $this->productRepoStub->method('findById')->willReturn($dto);
        $this->productRepoStub->method('findByIds')->willReturn([]);

        // change=-5 → newQty = 0 + (-5) = -5 <= 0 → remove branch
        $result = $this->service->updateItem(2, -5);

        $this->assertTrue($result->ok);
    }

    public function testSetQuantityDelegatesToUpdateItem(): void
    {
        $dto = new ProductDTO(
            id: 3,
            name: 'Hojicha',
            slug: 'hojicha',
            description: null,
            price: 4.0,
            category_id: 1,
            category_name: 'Bebidas',
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
        $this->productRepoStub->method('findById')->willReturn($dto);
        $this->productRepoStub->method('findByIds')->willReturn([]);

        $result = $this->service->setQuantity(3, 2);

        $this->assertTrue($result->ok);
    }

    public function testRemoveReturnsOkWhenItemNotInCart(): void
    {
        $result = $this->service->remove(999);

        $this->assertTrue($result->ok);
    }

    public function testClearReturnsOkAndCartBecomesEmpty(): void
    {
        $this->service->clear();

        $this->assertTrue($this->service->isEmpty());
    }

    public function testTransferToReservationReturnsTrueWhenCartIsEmpty(): void
    {
        $result = $this->service->transferToReservation(1);

        $this->assertTrue($result);
    }

    public function testGetWithDetailsReturnsEmptyStructureWhenCartIsEmpty(): void
    {
        $result = $this->service->getWithDetails();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertEmpty($result['items']);
        $this->assertSame(0, $result['totalQty']);
        $this->assertSame(0.0, $result['totalPrice']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['cart']);
    }

    public function testRemoveActuallyDeletesItemWhenItemIsInCart(): void
    {
        $_SESSION['cart'] = ['items' => [5 => 3], 'totalQty' => 3, 'totalPrice' => 15.0];
        $this->productRepoStub->method('findByIds')->willReturn([]);

        $result = $this->service->remove(5);

        $this->assertTrue($result->ok);
        $this->assertSame(0, $this->service->getQuantity(5));
        $this->assertTrue($this->service->isEmpty());
    }

    public function testGetWithDetailsReturnsDetailedItemsWhenCartHasProducts(): void
    {
        $_SESSION['cart'] = ['items' => [1 => 2], 'totalQty' => 2, 'totalPrice' => 1000.0];
        $this->productRepoStub->method('findByIds')->willReturn([
            1 => [
                'name'          => 'Matcha',
                'japanese_name' => '抹茶',
                'price'         => 500,
                'is_active'     => true,
                'image_url'     => null,
                'station'       => 'bar',
            ],
        ]);

        $result = $this->service->getWithDetails();

        $this->assertCount(1, $result['items']);
        $this->assertSame(1, $result['items'][0]['product_id']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame(1000, $result['items'][0]['subtotal']);
    }

    public function testUpdateItemCapsQuantityAtMaxQtyPerItem(): void
    {
        $_SESSION['cart'] = ['items' => [7 => 95], 'totalQty' => 95, 'totalPrice' => 0.0];
        $dto = new ProductDTO(
            id: 7,
            name: 'Sencha',
            slug: 'sencha',
            description: null,
            price: 4.0,
            category_id: 1,
            category_name: 'Bebidas',
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
        $this->productRepoStub->method('findById')->willReturn($dto);
        $this->productRepoStub->method('findByIds')->willReturn([
            7 => ['is_active' => true, 'product_type' => 'item', 'price' => 400],
        ]);

        // 95 + 10 = 105 → capped to 99
        $result = $this->service->updateItem(7, 10);

        $this->assertTrue($result->ok);
        $this->assertSame(99, $this->service->getQuantity(7));
    }

    public function testUpdateItemRejectsNewItemWhenMaxUniqueItemsReached(): void
    {
        // Fill cart with 50 unique items (IDs 100–149)
        $items = \array_combine(\range(100, 149), \array_fill(0, 50, 1));
        $_SESSION['cart'] = ['items' => $items, 'totalQty' => 50, 'totalPrice' => 0.0];

        $dto = new ProductDTO(
            id: 200,
            name: 'Genmaicha',
            slug: 'genmaicha',
            description: null,
            price: 3.0,
            category_id: 1,
            category_name: 'Bebidas',
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
        $this->productRepoStub->method('findById')->willReturn($dto);

        $result = $this->service->updateItem(200, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(0, $this->service->getQuantity(200));
    }

    public function testTransferToReservationCallsItemRepoAndClearsCart(): void
    {
        $_SESSION['cart'] = ['items' => [1 => 2], 'totalQty' => 2, 'totalPrice' => 1000.0];
        $this->productRepoStub->method('findByIds')->willReturn([
            1 => [
                'name'          => 'Matcha',
                'japanese_name' => '抹茶',
                'price'         => 500,
                'is_active'     => true,
                'image_url'     => null,
                'station'       => null,
            ],
        ]);

        $result = $this->service->transferToReservation(42);

        $this->assertTrue($result);
        $this->assertTrue($this->service->isEmpty());
    }

    public function testUpdateItemRecalculatesCartTotals(): void
    {
        $dto = new ProductDTO(
            id: 3,
            name: 'Hojicha',
            slug: 'hojicha',
            description: null,
            price: 5.0,
            category_id: 1,
            category_name: 'Bebidas',
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
        $this->productRepoStub->method('findById')->willReturn($dto);
        $this->productRepoStub->method('findByIds')->willReturn([
            3 => ['is_active' => true, 'product_type' => 'item', 'price' => 500],
        ]);

        $result = $this->service->updateItem(3, 2);

        $this->assertTrue($result->ok);
        $this->assertSame(2, $result->data['totalQty']);
        $this->assertSame(1000.0, $result->data['totalPrice']);
    }

    public function testRecalculateSkipsValidItemWithZeroQuantity(): void
    {
        // Producto 99 en sesión con qty=0 (válido: activo y tipo item, pero qty cero)
        $_SESSION['cart'] = ['items' => [99 => 0], 'totalQty' => 0, 'totalPrice' => 0.0];

        $dto = new ProductDTO(
            id: 1,
            name: 'Matcha',
            slug: 'matcha',
            description: null,
            price: 5.0,
            category_id: 1,
            category_name: 'Bebidas',
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
        $this->productRepoStub->method('findById')->willReturn($dto);
        // findByIds devuelve ambos productos como activos; producto 99 tiene qty=0 en recalculate()
        $this->productRepoStub->method('findByIds')->willReturn([
            99 => ['is_active' => true, 'product_type' => 'item', 'price' => 300],
            1  => ['is_active' => true, 'product_type' => 'item', 'price' => 500],
        ]);

        // updateItem(1, 1) añade producto 1; recalculate procesa [99=>0, 1=>1]
        // Producto 99: activo+item pero qty=0 → if ($qty <= 0) { continue; } (línea 315)
        $result = $this->service->updateItem(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(0, $this->service->getQuantity(99));
        $this->assertSame(1, $this->service->getQuantity(1));
    }
}
