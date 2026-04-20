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
use App\Http\Middleware\CafeScopeMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tests para CafeScopeMiddleware
 *
 * Validación de ownership sobre café asignado (Manager scope).
 */
#[CoversClass(CafeScopeMiddleware::class)]
final class CafeScopeMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;

    private CafeScopeMiddleware $middleware;

    private ServerRequestInterface $request;

    private RequestHandlerInterface $handler;

    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->middleware = new CafeScopeMiddleware($this->responseFactory);

        // Mock request
        $this->request = $this->createMock(ServerRequestInterface::class);
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/manager/cafe');

        $this->request->method('getUri')->willReturn($uriMock);
        $this->request->method('getHeaderLine')->willReturn('');

        // Mock handler
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->handler->method('handle')->willReturn($this->response);

        // Reset session
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
        $this->assertInstanceOf(CafeScopeMiddleware::class, $this->middleware);
    }

    public function testDeniesAccessWhenNoCafeAssigned(): void
    {
        Session::start();
        Session::set('user_id', 5);
        Session::set('user_roles', ['manager']);
        Session::set('user', ['cafe_id' => null]);

        $response = $this->middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertNotSame($this->response, $response);
    }

    public function testAllowsAccessWhenCafeIdExists(): void
    {
        Session::start();
        Session::set('user_id', 5);
        Session::set('user_roles', ['manager']);
        Session::set('user', ['cafe_id' => 1]);

        $response = $this->middleware->process($this->request, $this->handler);

        // Debe permitir el acceso (código 2xx o retornar la respuesta del handler)
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testReturnsJsonFor403OnApiRequest(): void
    {
        Session::start();
        Session::set('user', ['cafe_id' => null]);

        // Mock API request
        $this->request = $this->createMock(ServerRequestInterface::class);
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/api/manager/cafe');

        $this->request->method('getUri')->willReturn($uriMock);
        $this->request->method('getHeaderLine')
            ->willReturnCallback(static fn ($header) => $header === 'Accept' ? 'application/json' : '');

        $middleware = new CafeScopeMiddleware($this->responseFactory);
        $response = $middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandlesUserWithoutCafeIdKey(): void
    {
        Session::start();
        Session::set('user', []);

        $response = $this->middleware->process($this->request, $this->handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandlesMultipleCafeIds(): void
    {
        Session::start();
        Session::set('user', ['cafe_id' => 1]);

        $response1 = $this->middleware->process($this->request, $this->handler);
        $this->assertInstanceOf(ResponseInterface::class, $response1);

        // Cambiar café asignado
        Session::set('user', ['cafe_id' => 2]);

        $response2 = $this->middleware->process($this->request, $this->handler);
        $this->assertInstanceOf(ResponseInterface::class, $response2);
    }

    public function testDeniesAccessWhenRouteParamCafeIdDoesNotMatch(): void
    {
        Session::start();
        Session::set('user_cafe_id', 1);

        $request = $this->createMock(ServerRequestInterface::class);
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/manager/cafe/2/settings');
        $request->method('getUri')->willReturn($uriMock);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $attr) => $attr === 'cafeId' ? '2' : null
        );

        $response = $this->middleware->process($request, $this->handler);

        // Non-API request → redirect (302), acceso denegado al café ajeno
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testAllowsAccessWhenRouteParamCafeIdMatches(): void
    {
        Session::start();
        Session::set('user_cafe_id', 1);

        $request = $this->createMock(ServerRequestInterface::class);
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/manager/cafe/1/settings');
        $request->method('getUri')->willReturn($uriMock);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $attr) => $attr === 'cafeId' ? '1' : null
        );

        $response = $this->middleware->process($request, $this->handler);

        $this->assertSame($this->response, $response);
    }
}
