<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * CartService: lectura del carrito (get, isEmpty, getQuantity) controlando
 * el estado de la sesión directamente vía $_SESSION.
 *
 * ¿Qué me quieres demostrar?
 * Que los métodos de lectura leen correctamente el estado del carrito en sesión
 * y que el carrito vacío devuelve la estructura correcta con valores cero.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la estructura del carrito (keys items/totalQty/totalPrice),
 * si SESSION_KEY deja de ser 'cart', o si isEmpty cambia su lógica de evaluación.
 */

namespace Tests\Unit\Services;

use App\Services\CartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartService::class)]
final class CartServiceTest extends TestCase
{
    private CartService $service;

    protected function setUp(): void
    {
        // Limpiar la sesión antes de cada test
        $_SESSION = [];
        $this->service = new CartService();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ──────────────────────────────────────────────
    // get — estructura del carrito
    // ──────────────────────────────────────────────

    public function testGetDevuelveCarritoVacioCuandoNoHaySesion(): void
    {
        $cart = $this->service->get();

        $this->assertIsArray($cart);
        $this->assertArrayHasKey('items', $cart);
        $this->assertArrayHasKey('totalQty', $cart);
        $this->assertArrayHasKey('totalPrice', $cart);
        $this->assertEmpty($cart['items']);
        $this->assertSame(0, $cart['totalQty']);
        $this->assertSame(0.0, $cart['totalPrice']);
    }

    public function testGetDevuelveItemsDeSession(): void
    {
        $_SESSION['cart'] = [
            'items' => [5 => 2, 7 => 1],
            'totalQty' => 3,
            'totalPrice' => 15.50,
        ];

        $cart = $this->service->get();

        $this->assertSame([5 => 2, 7 => 1], $cart['items']);
        $this->assertSame(3, $cart['totalQty']);
    }

    // ──────────────────────────────────────────────
    // isEmpty
    // ──────────────────────────────────────────────

    public function testIsEmptyRetornaTrueCuandoCarritoVacio(): void
    {
        $this->assertTrue($this->service->isEmpty());
    }

    public function testIsEmptyRetornaFalseCuandoCarritoTieneItems(): void
    {
        $_SESSION['cart'] = [
            'items' => [3 => 1],
            'totalQty' => 1,
            'totalPrice' => 5.0,
        ];

        $this->assertFalse($this->service->isEmpty());
    }

    // ──────────────────────────────────────────────
    // getQuantity
    // ──────────────────────────────────────────────

    public function testGetQuantityRetornaCeroCuandoProductoNoEnCarrito(): void
    {
        $qty = $this->service->getQuantity(999);

        $this->assertSame(0, $qty);
    }

    public function testGetQuantityRetornaCantidadCorrectaCuandoProductoEnCarrito(): void
    {
        $_SESSION['cart'] = [
            'items' => [10 => 3],
            'totalQty' => 3,
            'totalPrice' => 9.0,
        ];

        $qty = $this->service->getQuantity(10);

        $this->assertSame(3, $qty);
    }
}
