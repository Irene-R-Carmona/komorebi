<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\Manager\ProductApiController delega a ProductServiceInterface.
 *
 * ¿Qué me quieres demostrar?
 * Que create() retorna 201, update() retorna 200, toggleAvailability() delega correctamente,
 * y que ValidationException produce 422 en create() y update().
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el bloque catch(ValidationException) o cambia el código de estado de las respuestas.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1\Manager;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\V1\Manager\ProductApiController;
use App\Services\Contracts\ProductServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ProductApiController::class)]
final class ProductApiControllerTest extends ControllerTestCase
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

    private function makeController(
        ?ProductServiceInterface $service = null
    ): ProductApiController {
        if ($service === null) {
            $service = $this->createStub(ProductServiceInterface::class);
            $service->method('create')->willReturn(42);
            $service->method('update')->willReturn(true);
            $service->method('toggleActive')->willReturn(true);
            $service->method('delete')->willReturn(true);
        }

        return new ProductApiController(new ResponseFactory(), $service);
    }

    // — create —

    public function test_create_returns_201_on_success(): void
    {
        $request = $this->makePostRequest('/api/v1/manager/products', ['name' => 'Latte']);
        $response = $this->makeController()->create($request);

        $this->assertSame(201, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertSame(42, $body['data']['product_id']);
    }

    public function test_create_returns_422_on_validation_error(): void
    {
        $service = $this->createStub(ProductServiceInterface::class);
        $service->method('create')->willThrowException(new ValidationException('Nombre requerido'));

        $request = $this->makePostRequest('/api/v1/manager/products', []);
        $response = $this->makeController($service)->create($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_create_strips_image_url(): void
    {
        $captured = null;
        $service = $this->createStub(ProductServiceInterface::class);
        $service->method('create')
            ->willReturnCallback(function (array $data) use (&$captured): int {
                $captured = $data;

                return 1;
            });

        $request = $this->makePostRequest('/api/v1/manager/products', [
            'name' => 'Latte',
            'image_url' => 'https://evil.com/img.jpg',
        ]);
        $this->makeController($service)->create($request);

        $this->assertArrayNotHasKey('image_url', $captured ?? []);
    }

    // — update —

    public function test_update_returns_200_on_success(): void
    {
        $request = $this->makePostRequest('/api/v1/manager/products/5', ['name' => 'Cappuccino']);
        $response = $this->makeController()->update($request, 5);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_update_returns_422_on_validation_error(): void
    {
        $service = $this->createStub(ProductServiceInterface::class);
        $service->method('update')->willThrowException(new ValidationException('Precio inválido'));

        $request = $this->makePostRequest('/api/v1/manager/products/5', ['price' => -1]);
        $response = $this->makeController($service)->update($request, 5);

        $this->assertSame(422, $response->getStatusCode());
    }

    // — toggleAvailability —

    public function test_toggleAvailability_returns_200_on_success(): void
    {
        $request = $this->makePostRequest('/api/v1/manager/products/3/toggle');
        $response = $this->makeController()->toggleAvailability($request, 3);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_toggleAvailability_returns_500_when_service_fails(): void
    {
        $service = $this->createStub(ProductServiceInterface::class);
        $service->method('toggleActive')->willReturn(false);

        $request = $this->makePostRequest('/api/v1/manager/products/3/toggle');
        $response = $this->makeController($service)->toggleAvailability($request, 3);

        $this->assertSame(500, $response->getStatusCode());
    }

    // — delete —

    public function test_delete_returns_200_on_success(): void
    {
        $request = $this->makePostRequest('/api/v1/manager/products/7');
        $response = $this->makeController()->delete($request, 7);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ProductApiController::class, 'create'));
        $this->assertTrue(\method_exists(ProductApiController::class, 'update'));
        $this->assertTrue(\method_exists(ProductApiController::class, 'toggleAvailability'));
        $this->assertTrue(\method_exists(ProductApiController::class, 'delete'));
    }
}
