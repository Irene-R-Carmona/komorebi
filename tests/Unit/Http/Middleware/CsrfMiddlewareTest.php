<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * El middleware CsrfMiddleware y sus caminos de validación CSRF.
 *
 * ¿Qué me quieres demostrar?
 * Que las rutas mutantes sin token CSRF válido reciben 403,
 * que los GET pasan siempre, y que las peticiones con auth_method=bearer
 * omiten la validación CSRF (para apps móviles/API clients).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el skip para Bearer, si se cambia el método HTTP verificado,
 * o si se modifica el código de respuesta de error CSRF.
 */

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\CsrfMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(CsrfMiddleware::class)]
final class CsrfMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
    }

    private function makeMiddleware(): CsrfMiddleware
    {
        return new CsrfMiddleware($this->responseFactory);
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    private function makeRequest(string $method, string $authMethod = ''): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getAttribute')->willReturnCallback(
            fn (string $attr) => $attr === 'auth_method' ? ($authMethod ?: null) : null
        );

        return $request;
    }

    public function test_get_request_passes_without_csrf_check(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('GET'),
            $this->makeHandler(200)
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_bearer_post_skips_csrf_and_passes(): void
    {
        // Bearer token auth — no hay sesión, no hay token CSRF; debe pasar igualmente
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('POST', 'bearer'),
            $this->makeHandler(200)
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_bearer_delete_skips_csrf_and_passes(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('DELETE', 'bearer'),
            $this->makeHandler(204)
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_bearer_patch_skips_csrf_and_passes(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('PATCH', 'bearer'),
            $this->makeHandler(200)
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_session_post_without_valid_csrf_returns_403(): void
    {
        // Sin auth_method=bearer y sin token CSRF válido → 403
        // No iniciamos sesión ni generamos token, Csrf::validate() devolverá false
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('POST', ''),
            $this->makeHandler(200)
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_session_put_without_valid_csrf_returns_403(): void
    {
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('PUT', ''),
            $this->makeHandler(200)
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}
