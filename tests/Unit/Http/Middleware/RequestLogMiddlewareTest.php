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

    private function makeRequest(string $method = 'GET', string $path = '/test', ?array $parsedBody = null): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getServerParams')->willReturn([]);
        $request->method('getParsedBody')->willReturn($parsedBody);

        return $request;
    }

    private function makeHandler(int $statusCode = 200, ?callable $onHandle = null): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        $handler = $this->createMock(RequestHandlerInterface::class);
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

    public function testSensitiveFieldsRedactedIn4xxBody(): void
    {
        // Accedemos al método privado estático via Reflection (PHP 8.1+ no requiere setAccessible)
        $ref = new \ReflectionMethod(RequestLogMiddleware::class, 'sanitizeBody');

        $body = [
            'email' => 'user@example.com',
            'password' => 'secret123',
            'token' => 'abc',
            '_token' => 'xyz',
            'cvv' => '123',
            'card_number' => '4111111111111111',
            'card_expiry' => '12/26',
            'secret' => 'mysecret',
            'authorization' => 'Bearer xyz',
            'current_password' => 'old',
            'new_password' => 'new',
            'name' => 'Ana García',
        ];

        /** @var array<string,mixed> $result */
        $result = $ref->invoke(null, $body);

        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('[REDACTED]', $result['token']);
        $this->assertSame('[REDACTED]', $result['_token']);
        $this->assertSame('[REDACTED]', $result['cvv']);
        $this->assertSame('[REDACTED]', $result['card_number']);
        $this->assertSame('[REDACTED]', $result['card_expiry']);
        $this->assertSame('[REDACTED]', $result['secret']);
        $this->assertSame('[REDACTED]', $result['authorization']);
        $this->assertSame('[REDACTED]', $result['current_password']);
        $this->assertSame('[REDACTED]', $result['new_password']);
        // Campo no sensible permanece intacto
        $this->assertSame('user@example.com', $result['email']);
        $this->assertSame('Ana García', $result['name']);
    }

    public function testBodyNotIncludedFor2xxResponses(): void
    {
        $body = ['password' => 'secret'];

        $capturedEvent = null;
        $handler = $this->makeHandler(200, function () use (&$capturedEvent) {
            $capturedEvent = WideEvent::all();
        });

        $middleware = new RequestLogMiddleware();
        $middleware->process($this->makeRequest('POST', '/login', $body), $handler);

        // Durante el handler (2xx), request_body NO debe estar en WideEvent todavía
        $this->assertArrayNotHasKey('request_body', $capturedEvent ?? []);
    }

    public function testNonArrayBodyDoesNotCauseErrorOn4xx(): void
    {
        // getParsedBody() puede retornar null para content-types no-form
        $middleware = new RequestLogMiddleware();

        $this->expectNotToPerformAssertions();

        $middleware->process(
            $this->makeRequest('POST', '/api/data', null),
            $this->makeHandler(400)
        );
    }
}
