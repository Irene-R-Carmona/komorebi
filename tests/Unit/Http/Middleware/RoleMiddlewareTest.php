<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los caminos del middleware RoleMiddleware: admin siempre pasa,
 * usuario con rol permitido pasa, usuario sin rol es redirigido.
 *
 * ¿Qué me quieres demostrar?
 * Que el control de roles por sesión funciona y que la redirección
 * se hace correctamente cuando falta el rol.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se modifica la lógica de verificación de roles, la URL de redirección
 * o el código HTTP de la respuesta de denegación.
 */

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Middleware\RoleMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RoleMiddleware::class)]
final class RoleMiddlewareTest extends TestCase
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

    private function makeMiddleware(array|string $allowedRoles = ['manager']): RoleMiddleware
    {
        return new RoleMiddleware($this->responseFactory, $allowedRoles);
    }

    private function makeHandler(): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    private function makeRequest(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }

    private function setSessionRoles(array $roles): void
    {
        Session::start();
        $_SESSION['user_roles'] = $roles;
    }

    public function testAdminAlwaysHasAccess(): void
    {
        $this->setSessionRoles(['admin']);

        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequest(),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUserWithAllowedRoleHasAccess(): void
    {
        $this->setSessionRoles(['manager']);

        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequest(),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUserWithOneOfMultipleAllowedRolesHasAccess(): void
    {
        $this->setSessionRoles(['editor']);

        $response = $this->makeMiddleware(['manager', 'editor'])->process(
            $this->makeRequest(),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUserWithoutAllowedRoleIsRedirected(): void
    {
        $this->setSessionRoles(['user']);

        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequest(),
            $this->makeHandler()
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
    }

    public function testEmptyRolesIsRedirected(): void
    {
        $this->setSessionRoles([]);

        $response = $this->makeMiddleware(['manager'])->process(
            $this->makeRequest(),
            $this->makeHandler()
        );

        self::assertSame(302, $response->getStatusCode());
    }

    public function testStringRoleIsNormalizedToArray(): void
    {
        $this->setSessionRoles(['staff']);

        $middleware = new RoleMiddleware($this->responseFactory, 'staff');
        $response = $middleware->process(
            $this->makeRequest(),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
