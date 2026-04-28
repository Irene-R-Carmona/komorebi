<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\IdempotencyMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ¿Qué pruebas aquí?
 * El middleware IdempotencyMiddleware y su lógica de validación y bypass.
 *
 * ¿Qué me quieres demostrar?
 * - Sin header Idempotency-Key → pasa transparentemente al handler.
 * - UUID v4 inválido → 422 sin llamar al handler.
 * - UUID v4 válido sin Redis disponible → pasa transparentemente al handler.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en la lógica de validación UUID, el código HTTP retornado
 * o el comportamiento cuando Redis no está disponible romperá estos tests.
 *
 * Nota: los tests con Redis real pertenecen a Integration (no a Unit).
 */
#[CoversClass(IdempotencyMiddleware::class)]
final class IdempotencyMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private RequestHandlerInterface $handler;
    private ResponseInterface $handlerResponse;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->handlerResponse = $this->responseFactory->createResponse(201);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->handlerResponse);
        $this->handler = $handler;
    }

    private function makeRequest(string $idempotencyKey): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->willReturnCallback(static function (string $header) use ($idempotencyKey): string {
                return \strtolower($header) === 'idempotency-key' ? $idempotencyKey : '';
            });

        return $request;
    }

    public function testAbsentHeaderPassesThrough(): void
    {
        $mw      = new IdempotencyMiddleware($this->responseFactory);
        $request = $this->makeRequest('');

        $response = $mw->process($request, $this->handler);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testWhitespaceOnlyHeaderPassesThrough(): void
    {
        $mw      = new IdempotencyMiddleware($this->responseFactory);
        $request = $this->makeRequest('   ');

        $response = $mw->process($request, $this->handler);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testInvalidKeyFormatReturns422(): void
    {
        $mw      = new IdempotencyMiddleware($this->responseFactory);
        $request = $this->makeRequest('not-a-uuid');

        $response = $mw->process($request, $this->handler);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testInvalidKeyV1UuidReturns422(): void
    {
        // UUID v1 (version digit = 1) → inválido para idempotencia
        $mw      = new IdempotencyMiddleware($this->responseFactory);
        $request = $this->makeRequest('550e8400-e29b-11d4-a716-446655440000');

        $response = $mw->process($request, $this->handler);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testValidUuidV4WithoutRedisPassesThrough(): void
    {
        // Sin Redis disponible (test en unit, sin infra) → pasa transparentemente
        $mw      = new IdempotencyMiddleware($this->responseFactory);
        $request = $this->makeRequest('550e8400-e29b-4d4a-a716-446655440000');

        $response = $mw->process($request, $this->handler);

        // Puede ser 201 (handler) o tener el header si Redis estuviese disponible.
        // En unit test sin Redis siempre llega al handler.
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testValidUuidV4UppercasePassesThrough(): void
    {
        $mw      = new IdempotencyMiddleware($this->responseFactory);
        $request = $this->makeRequest('550E8400-E29B-4D4A-A716-446655440000');

        $response = $mw->process($request, $this->handler);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testInvalidKeyBodyContainsCodeField(): void
    {
        $mw      = new IdempotencyMiddleware($this->responseFactory);
        $request = $this->makeRequest('invalid-key!!');

        $response = $mw->process($request, $this->handler);
        $body     = \json_decode((string) $response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertIsArray($body);
        $this->assertArrayHasKey('code', $body);
        $this->assertSame('invalid_idempotency_key', $body['code']);
        $this->assertFalse($body['ok']);
    }
}
