<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\LogContext;
use App\Core\WideEvent;
use App\Http\Middleware\RequestLogMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ¿Qué pruebas aquí?
 * Que RequestLogMiddleware genera un request_id, popula LogContext y WideEvent,
 * emite el canonical event y deja ambos registros limpios al terminar.
 *
 * ¿Qué me quieres demostrar?
 * Que el WideEvent acumula contexto de request durante el handler y queda vacío al final.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si el middleware deja de generar request_id, de poblar WideEvent, o de llamar reset().
 */
final class RequestLogMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        LogContext::reset();
        WideEvent::reset();
    }

    protected function tearDown(): void
    {
        LogContext::reset();
        WideEvent::reset();
    }

    private function makeRequest(string $method = 'GET', string $path = '/test'): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getServerParams')->willReturn([]);

        return $request;
    }

    private function makeHandler(int $statusCode = 200, ?callable $onHandle = null): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

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

        $handler = $this->makeHandler(200, function () use (&$capturedRequestId) {
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

        $handler = $this->makeHandler(200, function () use (&$capturedMethod, &$capturedPath) {
            $capturedMethod = LogContext::get('method');
            $capturedPath = LogContext::get('path');
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest('POST', '/api/users'), $handler);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/api/users', $capturedPath);
    }

    public function testPopulatesWideEventWithRequestContextDuringHandler(): void
    {
        $capturedEvent = null;

        $handler = $this->makeHandler(200, function () use (&$capturedEvent) {
            $capturedEvent = WideEvent::all();
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest('DELETE', '/api/cafes/5'), $handler);

        $this->assertNotNull($capturedEvent);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) ($capturedEvent['request_id'] ?? ''));
        $this->assertSame('DELETE', $capturedEvent['method']);
        $this->assertSame('/api/cafes/5', $capturedEvent['path']);
        $this->assertArrayHasKey('timestamp', $capturedEvent);
    }

    public function testResetsLogContextAfterResponse(): void
    {
        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame([], LogContext::all());
    }

    public function testResetsWideEventAfterResponse(): void
    {
        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame([], WideEvent::all());
    }

    public function testReturnsHandlerResponse(): void
    {
        $middleware = new RequestLogMiddleware();
        $response = $middleware->process($this->makeRequest(), $this->makeHandler(201));

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testWideEventRequestIdMatchesLogContextRequestId(): void
    {
        $wideEventId = null;
        $logContextId = null;

        $handler = $this->makeHandler(200, function () use (&$wideEventId, &$logContextId) {
            $wideEventId = WideEvent::all()['request_id'] ?? null;
            $logContextId = LogContext::get('request_id');
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $handler);

        $this->assertNotNull($wideEventId);
        $this->assertSame($wideEventId, $logContextId);
    }

    public function testWideEventPreservesManuallyAddedSectionsDuringHandler(): void
    {
        $capturedSection = null;

        $handler = $this->makeHandler(200, function () use (&$capturedSection) {
            WideEvent::setSection('reservation', ['id' => 99, 'cafe_id' => 3]);
            $capturedSection = WideEvent::all()['reservation'] ?? null;
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest(), $handler);

        // La sección fue visible durante el handler
        $this->assertSame(['id' => 99, 'cafe_id' => 3], $capturedSection);
        // Y el WideEvent queda vacío al final (reset)
        $this->assertSame([], WideEvent::all());
    }
}
