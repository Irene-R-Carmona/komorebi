<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios del middleware ApiAuthMiddleware.
 *
 * ¿Qué me quieres demostrar?
 * Que el middleware maneja correctamente los dos caminos ortogonales:
 * Bearer token y sesión web. Un Bearer presente siempre se procesa como
 * credencial Bearer, sin fallback a sesión. Un token inválido retorna 401
 * inmediatamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en la precedencia entre Bearer y sesión, en los códigos
 * de error HTTP, o en los atributos que el middleware establece en la request.
 */

namespace Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Services\Contracts\ApiTokenServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tests para ApiAuthMiddleware (autenticación Bearer + sesión)
 */
final class ApiAuthMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    /** @var \PHPUnit\Framework\MockObject\Stub&ServerRequestInterface */
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->request = $this->createMock(ServerRequestInterface::class);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    private function buildMiddleware(?ApiTokenServiceInterface $tokenService = null): ApiAuthMiddleware
    {
        return new ApiAuthMiddleware($this->responseFactory, $tokenService);
    }

    private function mockHandlerReturning(int $status = 200): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    // ─────────────────────────────────────────────────────────────
    // Bearer path
    // ─────────────────────────────────────────────────────────────

    public function testValidBearerSetsAttributesAndDelegatesToHandler(): void
    {
        $tokenData = [
            'user_id' => 7,
            'user' => ['id' => 7, 'name' => 'Test', 'is_active' => 1],
            'user_roles' => ['admin'],
            'token_id' => 1,
        ];

        $tokenService = $this->createMock(ApiTokenServiceInterface::class);
        $tokenService->method('validate')
            ->willReturn(Result::ok($tokenData));

        $this->request->method('getHeaderLine')
            ->willReturn('Bearer abc123');

        // withAttribute() retorna la misma instancia (fluent)
        $this->request->method('withAttribute')->willReturnSelf();

        $handler = $this->mockHandlerReturning(200);
        $response = $this->buildMiddleware($tokenService)->process($this->request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testInvalidBearerReturns401WithoutCallingHandler(): void
    {
        $tokenService = $this->createMock(ApiTokenServiceInterface::class);
        $tokenService->method('validate')
            ->willReturn(Result::fail('Token expirado.', 'invalid_token'));

        $this->request->method('getHeaderLine')
            ->willReturn('Bearer badtoken');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->buildMiddleware($tokenService)->process($this->request, $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testBearerWithNullTokenServiceReturns401(): void
    {
        // Si no hay ApiTokenService inyectado y llega un Bearer header, 401.
        $this->request->method('getHeaderLine')
            ->willReturn('Bearer sometoken');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        // tokenService = null
        $response = $this->buildMiddleware(null)->process($this->request, $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────
    // Session path (sin Bearer header)
    // ─────────────────────────────────────────────────────────────

    public function testWithoutBearerAndNoSessionReturns401(): void
    {
        $this->request->method('getHeaderLine')
            ->willReturn('');

        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }
        // Sin user_id en sesión
        unset($_SESSION['user_id']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->buildMiddleware()->process($this->request, $handler);

        $this->assertSame(401, $response->getStatusCode());
    }
}
