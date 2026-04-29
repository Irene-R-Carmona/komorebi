<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los caminos del middleware ApiRoleMiddleware: admin siempre pasa,
 * usuario con rol autorizado pasa, usuario sin rol recibe 403 JSON.
 *
 * ¿Qué me quieres demostrar?
 * Que el middleware usa el atributo user_roles del request (o sesión como
 * fallback), que admin siempre pasa, y que sin el rol recibe JSON 403.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se modifica la verificación de roles, el código 403, la clave JSON
 * de respuesta o se elimina el bypass de admin.
 */

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Middleware\ApiRoleMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ApiRoleMiddleware::class)]
final class ApiRoleMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();

        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    private function makeMiddleware(array|string $allowedRoles = ['manager']): ApiRoleMiddleware
    {
        return new ApiRoleMiddleware($this->responseFactory, $allowedRoles);
    }

    private function makeHandler(): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    private function makeRequestWithRoles(array $roles): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static function (string $attr, mixed $default = null) use ($roles): mixed {
                return $attr === 'user_roles' ? $roles : $default;
            }
        );

        return $request;
    }

    private function makeRequestWithNoRoleAttribute(): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        // Returns the default value when attribute is not set
        $request->method('getAttribute')->willReturnArgument(1);

        return $request;
    }

    public function testAdminAlwaysHasAccess(): void
    {
        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequestWithRoles(['admin']),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUserWithAllowedRoleHasAccess(): void
    {
        $response = $this->makeMiddleware(['editor'])->process(
            $this->makeRequestWithRoles(['editor']),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUserWithOneOfMultipleAllowedRolesHasAccess(): void
    {
        $response = $this->makeMiddleware(['manager', 'editor'])->process(
            $this->makeRequestWithRoles(['editor']),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUserWithoutAllowedRoleReturns403Json(): void
    {
        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequestWithRoles(['user']),
            $this->makeHandler()
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testEmptyRolesReturns403(): void
    {
        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequestWithRoles([]),
            $this->makeHandler()
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testStringRoleIsNormalizedToArray(): void
    {
        $response = new ApiRoleMiddleware($this->responseFactory, 'staff')->process(
            $this->makeRequestWithRoles(['staff']),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRolesFallBackToSessionWhenAttributeNotSet(): void
    {
        Session::start();
        $_SESSION['user_roles'] = ['manager'];

        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequestWithNoRoleAttribute(),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
