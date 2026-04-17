<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\PayloadSizeMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ¿Qué pruebas aquí?
 * El middleware PayloadSizeMiddleware y su lógica de control de tamaño.
 *
 * ¿Qué me quieres demostrar?
 * Que peticiones dentro del límite pasan al siguiente handler,
 * y peticiones demasiado grandes reciben 413 sin pasar al handler.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en la lógica de comparación de Content-Length
 * o en el código HTTP retornado romperá estos tests.
 */
final class PayloadSizeMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private RequestHandlerInterface $handler;
    private ResponseInterface $handlerResponse;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();

        $this->handlerResponse = $this->responseFactory->createResponse(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->handlerResponse);
        $this->handler = $handler;
    }

    private function makeRequest(string $contentLength): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->willReturnCallback(static function (string $header) use ($contentLength): string {
                return \strtolower($header) === 'content-length' ? $contentLength : '';
            });

        return $request;
    }

    public function testPayloadWithinLimitPassesThrough(): void
    {
        $mw = new PayloadSizeMiddleware($this->responseFactory, 256);
        $request = $this->makeRequest('1024'); // 1 KB

        $response = $mw->process($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPayloadExceedingLimitReturns413(): void
    {
        $mw = new PayloadSizeMiddleware($this->responseFactory, 1); // 1 KB limit
        $request = $this->makeRequest('2048'); // 2 KB

        $response = $mw->process($request, $this->handler);

        $this->assertSame(413, $response->getStatusCode());
    }

    public function testNoContentLengthHeaderPassesThrough(): void
    {
        $mw = new PayloadSizeMiddleware($this->responseFactory, 256);
        $request = $this->makeRequest('');

        $response = $mw->process($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testExactLimitBoundaryPassesThrough(): void
    {
        $mw = new PayloadSizeMiddleware($this->responseFactory, 1); // 1 KB = 1024 bytes
        $request = $this->makeRequest('1024'); // exactly 1 KB

        $response = $mw->process($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOneByteBeyondLimitReturns413(): void
    {
        $mw = new PayloadSizeMiddleware($this->responseFactory, 1); // 1 KB = 1024 bytes
        $request = $this->makeRequest('1025'); // 1 byte over

        $response = $mw->process($request, $this->handler);

        $this->assertSame(413, $response->getStatusCode());
    }

    public function testDefaultLimitIs256Kb(): void
    {
        $mw = new PayloadSizeMiddleware($this->responseFactory); // default = 256 KB
        $request = $this->makeRequest((string) (256 * 1024 + 1)); // 1 byte over default

        $response = $mw->process($request, $this->handler);

        $this->assertSame(413, $response->getStatusCode());
    }
}
