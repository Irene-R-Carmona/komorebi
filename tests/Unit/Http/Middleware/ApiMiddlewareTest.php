<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los caminos del middleware ApiMiddleware: petición válida API vs no-API.
 *
 * ¿Qué me quieres demostrar?
 * Que peticiones sin cabeceras API reciben 400, y que cualquiera de las tres
 * cabeceras válidas (Accept, Content-Type, X-Requested-With) permiten el paso.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se modifica qué cabeceras se consideran "API" o el código de error.
 */

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\ApiMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ApiMiddleware::class)]
final class ApiMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
    }

    private function makeMiddleware(): ApiMiddleware
    {
        return new ApiMiddleware($this->responseFactory);
    }

    private function makeHandler(): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    private function makeRequest(string $accept = '', string $contentType = '', string $xRequested = ''): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            static function (string $header) use ($accept, $contentType, $xRequested): string {
                return match ($header) {
                    'Accept' => $accept,
                    'Content-Type' => $contentType,
                    'X-Requested-With' => $xRequested,
                    default => '',
                };
            }
        );

        return $request;
    }

    public function testNonApiRequestReturns400Json(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest(),
            $this->makeHandler()
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testRequestWithJsonAcceptHeaderPasses(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest(accept: 'application/json'),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestWithJsonContentTypeHeaderPasses(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest(contentType: 'application/json'),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestWithXmlHttpRequestHeaderPasses(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest(xRequested: 'XMLHttpRequest'),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testXmlHttpRequestHeaderIsCaseInsensitive(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest(xRequested: 'xmlhttprequest'),
            $this->makeHandler()
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
