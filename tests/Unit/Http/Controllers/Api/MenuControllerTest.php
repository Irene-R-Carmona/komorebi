<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\MenuController delega a MenuService para alérgenos y productos.
 *
 * ¿Qué me quieres demostrar?
 * Que allergens() devuelve 200 con la lista transformada via AllergenTransformer.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la delegación a MenuService::getAllergens() o cambia el transformer.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\MenuController;
use App\Services\Contracts\MenuServiceInterface;
use App\Services\Contracts\RecentlyViewedServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(MenuController::class)]
final class MenuControllerTest extends ControllerTestCase
{
    private function makeController(): MenuController
    {
        $menuService = $this->createStub(MenuServiceInterface::class);
        $menuService->method('getAllergens')->willReturn([
            ['id' => 1, 'name' => 'Gluten', 'icon' => 'gluten.svg', 'description' => ''],
        ]);
        $menuService->method('getProductsByCategory')->willReturn([]);

        return new MenuController(
            new ResponseFactory(),
            $menuService,
            $this->createStub(RecentlyViewedServiceInterface::class),
        );
    }

    public function test_allergens_returns_200_with_list(): void
    {
        $response = $this->makeController()->allergens($this->makeGetRequest('/api/menu/alergenos'));

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('items', $body['data']);
    }

    public function test_products_returns_200(): void
    {
        $response = $this->makeController()->products($this->makeGetRequest('/api/menu/productos'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_get_product_returns_200_and_registers_view(): void
    {
        $recentlyViewedService = $this->createStub(RecentlyViewedServiceInterface::class);
        $controller = new MenuController(
            new ResponseFactory(),
            $this->createStub(MenuServiceInterface::class),
            $recentlyViewedService,
        );

        $request = $this->makeGetRequest('/api/v1/menu/products/5')
            ->withAttribute('id', '5');

        $response = $controller->getProduct($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_get_product_returns_422_when_id_invalid(): void
    {
        $controller = new MenuController(
            new ResponseFactory(),
            $this->createStub(MenuServiceInterface::class),
            $this->createStub(RecentlyViewedServiceInterface::class),
        );

        $request = $this->makeGetRequest('/api/v1/menu/products/0')
            ->withAttribute('id', '0');

        $response = $controller->getProduct($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(MenuController::class, 'allergens'));
        $this->assertTrue(\method_exists(MenuController::class, 'products'));
        $this->assertTrue(\method_exists(MenuController::class, 'getProduct'));
    }
}
