<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Api/FavoriteController.
 *
 * ¿Qué me quieres demostrar?
 * Que toggle() y list() devuelven 401 cuando user_id no está en los atributos
 * de la request, y que usan el atributo user_id de la request (no Session).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se restaura Session::userId() o se cambia la fuente del user_id,
 * o si se elimina la comprobación de autenticación.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(FavoriteController::class)]
final class FavoriteControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): FavoriteController
    {
        return new FavoriteController(
            new ResponseFactory(),
            $this->createStub(FavoriteRepositoryInterface::class),
        );
    }

    // ── Sin autenticación (atributo user_id ausente) ─────────────────────

    public function test_toggle_returns_401_when_not_authenticated(): void
    {
        // Sin atributo user_id en la request — el controller debe retornar 401
        $result = $this->makeController()->add(
            new ServerRequest('PUT', '/api/v1/favorites/1')
        );

        $this->assertSame(401, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertSame(401, $body['status']);
    }

    public function test_list_returns_401_when_not_authenticated(): void
    {
        $result = $this->makeController()->list(
            new ServerRequest('GET', '/api/v1/favorites')
        );

        $this->assertSame(401, $result->getStatusCode());
    }

    // ── Con user_id en atributos de la request ────────────────────────────

    public function test_add_uses_request_attribute_user_id_not_session(): void
    {
        // Poblamos la sesión con un user_id DIFERENTE al del atributo
        // Si el controller usa Session::userId() fallará porque la sesión no tiene user_id
        // pero con el atributo debe funcionar correctamente (no necesariamente 401).
        $_SESSION = [];

        $repo = $this->createStub(FavoriteRepositoryInterface::class);
        $repo->method('add')->willReturn(true);

        $controller = new FavoriteController(new ResponseFactory(), $repo);

        // Construir request con user_id + id en atributos (como lo haría ApiAuthMiddleware + Router)
        $request = new ServerRequest('PUT', '/api/v1/favorites/1')
            ->withAttribute('user_id', 42)
            ->withAttribute('id', '1');

        $response = $controller->add($request);

        // Debe retornar éxito (no 401) porque user_id está en atributos
        $this->assertNotSame(401, $response->getStatusCode());
    }

    public function test_list_uses_request_attribute_user_id_not_session(): void
    {
        $_SESSION = [];

        $repo = $this->createStub(FavoriteRepositoryInterface::class);
        $repo->method('getByUser')->willReturn([]);

        $controller = new FavoriteController(new ResponseFactory(), $repo);

        $request = new ServerRequest('GET', '/api/v1/favorites')
            ->withAttribute('user_id', 42);

        $response = $controller->list($request);

        $this->assertNotSame(401, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(FavoriteController::class, 'add'));
        $this->assertTrue(\method_exists(FavoriteController::class, 'remove'));
        $this->assertTrue(\method_exists(FavoriteController::class, 'list'));
    }

    public function test_add_returns_401_when_not_authenticated(): void
    {
        $result = $this->makeController()->add(
            new ServerRequest('PUT', '/api/v1/favorites/1')
        );

        $this->assertSame(401, $result->getStatusCode());
    }

    public function test_add_returns_200_when_id_in_route_attribute(): void
    {
        $repo = $this->createStub(FavoriteRepositoryInterface::class);
        $repo->method('add')->willReturn(true);
        $controller = new FavoriteController(new ResponseFactory(), $repo);
        $request = new ServerRequest('PUT', '/api/v1/favorites/5')
            ->withAttribute('user_id', 42)
            ->withAttribute('id', '5');

        $response = $controller->add($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_add_returns_422_when_id_is_zero(): void
    {
        $request = new ServerRequest('PUT', '/api/v1/favorites/0')
            ->withAttribute('user_id', 42)
            ->withAttribute('id', '0');

        $response = $this->makeController()->add($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_remove_returns_401_when_not_authenticated(): void
    {
        $result = $this->makeController()->remove(
            new ServerRequest('DELETE', '/api/v1/favorites/1')
        );

        $this->assertSame(401, $result->getStatusCode());
    }

    public function test_remove_returns_200_when_id_in_route_attribute(): void
    {
        $repo = $this->createStub(FavoriteRepositoryInterface::class);
        $repo->method('remove')->willReturn(true);
        $controller = new FavoriteController(new ResponseFactory(), $repo);
        $request = new ServerRequest('DELETE', '/api/v1/favorites/5')
            ->withAttribute('user_id', 42)
            ->withAttribute('id', '5');

        $response = $controller->remove($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_remove_returns_422_when_id_is_zero(): void
    {
        $request = new ServerRequest('DELETE', '/api/v1/favorites/0')
            ->withAttribute('user_id', 42)
            ->withAttribute('id', '0');

        $response = $this->makeController()->remove($request);

        $this->assertSame(422, $response->getStatusCode());
    }
}
