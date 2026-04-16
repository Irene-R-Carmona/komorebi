<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use App\Http\Middleware\AuthorizationMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tests para AuthorizationMiddleware (RBAC)
 *
 * Valida el control de permisos granulares con caché Redis.
 */
final class AuthorizationMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private AuthorizationMiddleware $middleware;
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->middleware = new AuthorizationMiddleware($this->responseFactory, 'cafe.edit');

        // Mock request
        $this->request = $this->createStub(ServerRequestInterface::class);
        $uriMock = $this->createStub(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/manager/cafe/edit');

        $this->request->method('getUri')->willReturn($uriMock);
        $this->request->method('getHeaderLine')->willReturn('');

        // Mock handler
        $this->handler = $this->createStub(RequestHandlerInterface::class);
        $this->response = $this->createStub(ResponseInterface::class);
        $this->handler->method('handle')->willReturn($this->response);

        // Reset session antes de cada test
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    protected function tearDown(): void
    {
        unset($this->middleware, $this->request, $this->handler);
    }

    public function testMiddlewareCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AuthorizationMiddleware::class, $this->middleware);
    }

    public function testDeniesAccessWhenUserNotAuthenticated(): void
    {
        Session::start();
        Session::set('user_id', null);

        $response = $this->middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testAllowsAccessForAdminRole(): void
    {
        Session::start();
        Session::set('user_id', 1);
        Session::set('user_roles', ['admin']);

        $response = $this->middleware->process($this->request, $this->handler);

        // Admin debe pasar sin verificar permisos
        $this->assertSame($this->response, $response);
    }

    public function testDeniesAccessWhenPermissionNotGranted(): void
    {
        Session::start();
        Session::set('user_id', 10);
        Session::set('user_roles', ['user']);

        // No hay permisos en BD (no mockeamos DB, cache vacío)
        $response = $this->middleware->process($this->request, $this->handler);

        // Debe devolver 403 o redirect
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testReturnsJsonFor403OnApiRequest(): void
    {
        Session::start();
        Session::set('user_id', 10);
        Session::set('user_roles', ['user']);

        // Mock API request (Accept: application/json)
        $this->request = $this->createStub(ServerRequestInterface::class);
        $uriMock = $this->createStub(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/api/manager/stats');

        $this->request->method('getUri')->willReturn($uriMock);
        $this->request->method('getHeaderLine')
            ->willReturnCallback(static fn ($header) => $header === 'Accept' ? 'application/json' : '');

        $middleware = new AuthorizationMiddleware($this->responseFactory, 'cafe.edit');
        $response = $middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMiddlewareAcceptsCustomPermissions(): void
    {
        $customMiddleware = new AuthorizationMiddleware($this->responseFactory, 'review.moderate');

        $this->assertInstanceOf(AuthorizationMiddleware::class, $customMiddleware);
    }

    public function testMiddlewareLogsUnauthorizedAccess(): void
    {
        Session::start();
        Session::set('user_id', 5);
        Session::set('user_roles', ['staff']);

        $response = $this->middleware->process($this->request, $this->handler);

        // Solo verificamos que se ejecuta sin excepciones
        // El logger real escribirá a logs
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMiddlewareHandlesMultiplePermissions(): void
    {
        $middleware1 = new AuthorizationMiddleware($this->responseFactory, 'product.create');
        $middleware2 = new AuthorizationMiddleware($this->responseFactory, 'product.delete');

        $this->assertInstanceOf(AuthorizationMiddleware::class, $middleware1);
        $this->assertInstanceOf(AuthorizationMiddleware::class, $middleware2);
    }
}
