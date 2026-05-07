<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Services\Contracts\ApiTokenServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ¿Qué pruebas aquí?
 * El middleware ApiAuthMiddleware en su path de Bearer token.
 *
 * ¿Qué me quieres demostrar?
 * Que tokens inválidos retornan 401 problem+json, y que tokens válidos
 * enriquecen el request con user_id, user_roles y auth_method.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en la integración con ApiTokenServiceInterface,
 * en el código HTTP retornado, en el formato de error o en los atributos
 * añadidos al request romperá estos tests.
 */
#[CoversClass(ApiAuthMiddleware::class)]
final class ApiAuthMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
    }

    private function requestWithBearer(string $token): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnMap([
            ['Authorization', 'Bearer ' . $token],
        ]);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);
        $request->method('getRequestTarget')->willReturn('/api/v1/test');
        $request->method('withAttribute')->willReturnSelf();

        return $request;
    }

    /** @phpstan-ignore method.unused */
    private function requestWithoutAuth(): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);
        $request->method('getRequestTarget')->willReturn('/api/v1/test');

        return $request;
    }

    public function testBearerTokenServiceNullReturns401(): void
    {
        $mw = new ApiAuthMiddleware($this->responseFactory, null);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $response = $mw->process($this->requestWithBearer('sometoken'), $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'application/problem+json',
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function testBearerInvalidTokenReturns401(): void
    {
        $tokenService = $this->createStub(ApiTokenServiceInterface::class);
        $tokenService->method('validate')->willReturn(Result::fail('Token inválido o expirado.', 'invalid_token'));

        $mw = new ApiAuthMiddleware($this->responseFactory, $tokenService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $response = $mw->process($this->requestWithBearer('badtoken'), $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'application/problem+json',
            $response->getHeaderLine('Content-Type'),
        );
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertSame(401, $body['status'] ?? null);
        $this->assertSame('invalid_token', $body['code'] ?? null);
    }

    public function testBearerValidTokenPassesToHandler(): void
    {
        $tokenService = $this->createStub(ApiTokenServiceInterface::class);
        $tokenService->method('validate')->willReturn(Result::ok([
            'user_id' => 7,
            'user' => ['id' => 7, 'name' => 'Ana'],
            'user_roles' => ['admin'],
            'token_id' => 1,
        ]));

        $mw = new ApiAuthMiddleware($this->responseFactory, $tokenService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->responseFactory->createResponse(200));

        $response = $mw->process($this->requestWithBearer('validtoken'), $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testBearerValidTokenSetsAuthMethodAttribute(): void
    {
        $tokenService = $this->createStub(ApiTokenServiceInterface::class);
        $tokenService->method('validate')->willReturn(Result::ok([
            'user_id' => 5,
            'user' => ['id' => 5, 'name' => 'Bot'],
            'user_roles' => ['manager'],
            'token_id' => 2,
        ]));

        $mw = new ApiAuthMiddleware($this->responseFactory, $tokenService);

        $capturedAttribute = null;
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturnMap([
            ['Authorization', 'Bearer validtoken'],
        ]);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);
        $request->method('getRequestTarget')->willReturn('/api/v1/test');
        $request->method('withAttribute')->willReturnCallback(
            function (string $name, mixed $value) use ($request, &$capturedAttribute): ServerRequestInterface {
                if ($name === 'auth_method') {
                    $capturedAttribute = $value;
                }

                return $request;
            }
        );

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->responseFactory->createResponse(200));

        $mw->process($request, $handler);

        $this->assertSame('bearer', $capturedAttribute);
    }
}
