<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\Ops\KitchenApiController delega a KitchenServiceInterface
 * con la validación de inputs correcta.
 *
 * ¿Qué me quieres demostrar?
 * Que activeOrders() retorna 403 sin café asignado y 200 con las órdenes.
 * Que completeOrder() retorna 400 con id inválido, 422 si el servicio falla y 200 en éxito.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guard de cafe_id, o cambia la validación de id,
 * o si el mapeo de errores del servicio cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1\Ops;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\Ops\KitchenApiController;
use App\Services\Contracts\KitchenServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(KitchenApiController::class)]
final class KitchenApiControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(?KitchenServiceInterface $service = null): KitchenApiController
    {
        $service ??= $this->createStub(KitchenServiceInterface::class);

        return new KitchenApiController(new ResponseFactory(), $service);
    }

    // — activeOrders —

    public function test_activeOrders_returns_403_without_cafe(): void
    {
        $request = $this->makeGetRequest('/api/v1/ops/kitchen/orders');
        $response = $this->makeController()->activeOrders($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_activeOrders_returns_200_with_orders(): void
    {
        $this->asUser(userId: 1, role: 'kitchen', cafeId: 3);

        $service = $this->createStub(KitchenServiceInterface::class);
        $service->method('getAllPending')->willReturn([['id' => 10, 'name' => 'Café latte']]);

        $request = $this->makeGetRequest('/api/v1/ops/kitchen/orders');
        $response = new KitchenApiController(new ResponseFactory(), $service)->activeOrders($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // — completeOrder —

    public function test_completeOrder_returns_400_with_invalid_id(): void
    {
        $request = $this->makePostRequest('/api/v1/ops/kitchen/orders/0/complete', []);
        $response = $this->makeController()->completeOrder($request, 0);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_completeOrder_returns_422_when_service_returns_false(): void
    {
        $service = $this->createStub(KitchenServiceInterface::class);
        $service->method('markReady')->willReturn(false);

        $request = $this->makePostRequest('/api/v1/ops/kitchen/orders/5/complete', []);
        $response = new KitchenApiController(new ResponseFactory(), $service)->completeOrder($request, 5);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_completeOrder_returns_200_when_service_succeeds(): void
    {
        $service = $this->createStub(KitchenServiceInterface::class);
        $service->method('markReady')->willReturn(true);

        $request = $this->makePostRequest('/api/v1/ops/kitchen/orders/5/complete', []);
        $response = new KitchenApiController(new ResponseFactory(), $service)->completeOrder($request, 5);

        $this->assertSame(200, $response->getStatusCode());
    }
}
