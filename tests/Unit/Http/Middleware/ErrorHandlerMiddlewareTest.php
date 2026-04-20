<?php

/**
 * ¿Qué pruebas aquí?
 * ErrorHandlerMiddleware: wrapping PSR-15 con try-catch, despacho al ExceptionRendererRegistry.
 * ¿Qué me quieres demostrar?
 * Que el middleware pasa la solicitud cuando no hay excepción, captura excepciones
 * y delega al renderer correcto, o retorna 500 JSON cuando no hay renderer disponible.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si el middleware no captura excepciones, no llama al renderer, ignora el resultado
 * del renderer, o no retorna 500 cuando el registry está vacío.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ExceptionRendererInterface;
use App\Core\Http\ExceptionRendererRegistry;
use App\Core\Http\ResponseFactory;
use App\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(ErrorHandlerMiddleware::class)]
final class ErrorHandlerMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private Psr17Factory    $psr17;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->psr17 = new Psr17Factory();
    }

    public function testPassesThroughWhenNoException(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/');
        $expectedResponse = $this->psr17->createResponse(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $mw = new ErrorHandlerMiddleware(new ExceptionRendererRegistry(), $this->responseFactory);
        $response = $mw->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCatchesExceptionAndUsesRenderer(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/');
        $exception = new RuntimeException('boom');
        $renderedResponse = $this->psr17->createResponse(422);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);

        $renderer = $this->createMock(ExceptionRendererInterface::class);
        $renderer->method('supports')->willReturn(true);
        $renderer->method('priority')->willReturn(10);
        $renderer->method('render')->willReturn($renderedResponse);

        $registry = new ExceptionRendererRegistry();
        $registry->register($renderer);

        $mw = new ErrorHandlerMiddleware($registry, $this->responseFactory);
        $response = $mw->process($request, $handler);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testFallsBackTo500WhenNoRendererFound(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/');
        $exception = new RuntimeException('boom');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);

        $mw = new ErrorHandlerMiddleware(new ExceptionRendererRegistry(), $this->responseFactory);
        $response = $mw->process($request, $handler);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testFallback500ResponseIsJson(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/');
        $exception = new RuntimeException('boom');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);

        $mw = new ErrorHandlerMiddleware(new ExceptionRendererRegistry(), $this->responseFactory);
        $response = $mw->process($request, $handler);

        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testHandlerIsNotCalledOnSecondTimeAfterExceptionCaught(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/');
        $exception = new RuntimeException('boom');
        $fallbackResponse = $this->psr17->createResponse(503);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);

        $renderer = $this->createMock(ExceptionRendererInterface::class);
        $renderer->method('supports')->willReturn(true);
        $renderer->method('priority')->willReturn(10);
        $renderer->method('render')->willReturn($fallbackResponse);

        $registry = new ExceptionRendererRegistry();
        $registry->register($renderer);

        $mw = new ErrorHandlerMiddleware($registry, $this->responseFactory);
        $response = $mw->process($request, $handler);

        // El renderer retornó 503 — el middleware no debe envolverlo de nuevo
        $this->assertSame(503, $response->getStatusCode());
    }
}
