<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\LogContext;
use App\Http\Middleware\RequestLogMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ¿Qué pruebas aquí?
 * Que RequestLogMiddleware genera un request_id, popula LogContext y lo resetea al final.
 *
 * ¿Qué me quieres demostrar?
 * Que cada request tiene un request_id único y que LogContext queda limpio al terminar.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si el middleware deja de generar request_id, de usar LogContext o de llamar reset().
 */
final class RequestLogMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        LogContext::reset();
    }

    private function makeRequest(string $method = 'GET', string $path = '/test'): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private function makeHandler(?callable $onHandle = null): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function () use ($response, $onHandle) {
            if ($onHandle !== null) {
                ($onHandle)();
            }

            return $response;
        });

        return $handler;
    }

    public function testSetsRequestIdInLogContext(): void
    {
        $capturedRequestId = null;

        $handler = $this->makeHandler(function () use (&$capturedRequestId) {
            $capturedRequestId = LogContext::get('request_id');
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $handler);

        $this->assertNotNull($capturedRequestId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $capturedRequestId);
    }

    public function testSetsMethodAndPathInLogContext(): void
    {
        $capturedMethod = null;
        $capturedPath = null;

        $handler = $this->makeHandler(function () use (&$capturedMethod, &$capturedPath) {
            $capturedMethod = LogContext::get('method');
            $capturedPath = LogContext::get('path');
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest('POST', '/api/users'), $handler);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/api/users', $capturedPath);
    }

    public function testResetsLogContextAfterResponse(): void
    {
        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame([], LogContext::all());
    }

    public function testReturnsHandlerResponse(): void
    {
        $middleware = new RequestLogMiddleware();
        $response = $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
