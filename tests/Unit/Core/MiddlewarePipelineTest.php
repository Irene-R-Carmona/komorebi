<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * MiddlewarePipeline PSR-15: pipeline vacío, con un middleware,
 * con múltiples middlewares, con callable y LogicException sin handler.
 *
 * ¿Qué me quieres demostrar?
 * Que la cadena de middlewares se ejecuta en orden y que el finalHandler
 * se invoca correctamente al final.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se modifica el orden de ejecución, la delegación al finalHandler
 * o la excepción cuando no hay handler configurado.
 */

namespace Tests\Unit\Core;

use App\Core\MiddlewarePipeline;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(MiddlewarePipeline::class)]
final class MiddlewarePipelineTest extends TestCase
{
    private function makeRequest(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }

    private function makeResponse(int $status = 200): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        return $response;
    }

    private function makeFinalHandler(int $status = 200): RequestHandlerInterface
    {
        $response = $this->makeResponse($status);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    public function testEmptyPipelineWithFinalHandlerCallsFinalHandler(): void
    {
        $pipeline = new MiddlewarePipeline($this->makeFinalHandler(200));

        $response = $pipeline->handle($this->makeRequest());

        self::assertSame(200, $response->getStatusCode());
    }

    public function testEmptyPipelineWithoutFinalHandlerThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);

        $pipeline = new MiddlewarePipeline();
        $pipeline->handle($this->makeRequest());
    }

    public function testPipeMiddlewareInterfaceIsCalledWithRequest(): void
    {
        $finalResponse = $this->makeResponse(200);
        $finalHandler = $this->makeFinalHandler(200);

        $middleware = new class($finalResponse) implements MiddlewareInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            #[Override]
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($middleware);

        $response = $pipeline->handle($this->makeRequest());

        self::assertSame($finalResponse, $response);
    }

    public function testPipeMiddlewareCanCallNextHandler(): void
    {
        $finalHandler = $this->makeFinalHandler(200);

        $middleware = new class implements MiddlewareInterface {
            #[Override]
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($middleware);

        $response = $pipeline->handle($this->makeRequest());

        self::assertSame(200, $response->getStatusCode());
    }

    public function testCallableMiddlewareIsExecuted(): void
    {
        $expectedResponse = $this->makeResponse(418);
        $finalHandler = $this->makeFinalHandler(200);

        $callable = static function (ServerRequestInterface $req, RequestHandlerInterface $next) use ($expectedResponse): ResponseInterface {
            return $expectedResponse;
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($callable);

        $response = $pipeline->handle($this->makeRequest());

        self::assertSame(418, $response->getStatusCode());
    }

    public function testCallableMiddlewareCanDelegateToNext(): void
    {
        $finalHandler = $this->makeFinalHandler(200);

        $callable = static function (ServerRequestInterface $req, RequestHandlerInterface $next): ResponseInterface {
            return $next->handle($req);
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($callable);

        $response = $pipeline->handle($this->makeRequest());

        self::assertSame(200, $response->getStatusCode());
    }

    public function testMultipleMiddlewaresExecuteInOrder(): void
    {
        $log = [];
        $finalHandler = $this->makeFinalHandler(200);

        $first = static function (ServerRequestInterface $req, RequestHandlerInterface $next) use (&$log): ResponseInterface {
            $log[] = 'first';
            return $next->handle($req);
        };

        $second = static function (ServerRequestInterface $req, RequestHandlerInterface $next) use (&$log): ResponseInterface {
            $log[] = 'second';
            return $next->handle($req);
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($first)->pipe($second);

        $pipeline->handle($this->makeRequest());

        self::assertSame(['first', 'second'], $log);
    }

    public function testPipeReturnsSelfForChaining(): void
    {
        $pipeline = new MiddlewarePipeline();

        $callable = static fn($req, $next) => $next->handle($req);
        $result = $pipeline->pipe($callable);

        self::assertSame($pipeline, $result);
    }
}
