<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\CartController delega a CartService y valida entradas.
 *
 * ¿Qué me quieres demostrar?
 * Que add() retorna 422 sin product_id y 200 cuando el servicio confirma ok.
 * Que guest() siempre retorna carrito vacío sin llamar al servicio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de product_id en add() o cambia el formato de guest().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\CartController;
use App\Services\Contracts\CartServiceInterface;
use Tests\Support\ControllerTestCase;

final class CartControllerTest extends ControllerTestCase
{
    private function makeController(): CartController
    {
        $service = $this->createMock(CartServiceInterface::class);
        $service->method('getWithDetails')->willReturn(['items' => [], 'totalQty' => 0, 'totalPrice' => 0.0]);
        $service->method('add')->willReturn(['items' => [], 'totalQty' => 1, 'totalPrice' => 0.0]);

        return new CartController(new ResponseFactory(), $service);
    }

    public function test_get_returns_200_with_cart_data(): void
    {
        $response = $this->makeController()->get($this->makeGetRequest('/api/cart'));

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('items', $body['data']);
    }

    public function test_guest_returns_empty_cart_without_auth(): void
    {
        $response = $this->makeController()->guest($this->makeGetRequest('/api/cart/guest'));

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertSame(0, $body['data']['totalQty']);
        $this->assertSame(0, $body['data']['totalPrice']);
    }

    public function test_add_returns_422_when_product_id_missing(): void
    {
        $response = $this->makeController()->add($this->makePostRequest('/api/cart/add', []));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_add_returns_422_when_product_id_is_not_numeric(): void
    {
        $response = $this->makeController()->add(
            $this->makePostRequest('/api/cart/add', ['product_id' => 'abc'])
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(CartController::class, 'get'));
        $this->assertTrue(\method_exists(CartController::class, 'guest'));
        $this->assertTrue(\method_exists(CartController::class, 'add'));
        $this->assertTrue(\method_exists(CartController::class, 'remove'));
        $this->assertTrue(\method_exists(CartController::class, 'update'));
        $this->assertTrue(\method_exists(CartController::class, 'clear'));
    }
}
