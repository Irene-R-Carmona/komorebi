<?php

/**
 * ¿Qué pruebas aquí?
 * Tests de Api/V1/TokenController: list, create y revoke con validaciones y Result pattern.
 *
 * ¿Qué me quieres demostrar?
 * - list() devuelve 200 con el array de tokens del servicio.
 * - create() lanza ValidationException cuando "name" está vacío o falta.
 * - create() devuelve 201 con el plain token UNA sola vez.
 * - revoke() devuelve 200 cuando el servicio confirma la revocación.
 * - revoke() devuelve 404 cuando el servicio devuelve Result::fail.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan las validaciones de "name" en create(),
 * si cambian los códigos HTTP, o si cambia el contrato de ApiTokenServiceInterface.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\V1\TokenController;
use App\Services\Contracts\ApiTokenServiceInterface;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;

final class TokenControllerTest extends ControllerTestCase
{
    private function makeController(): TokenController
    {
        $tokenService = $this->createMock(ApiTokenServiceInterface::class);
        $tokenService->method('listForUser')->willReturn([]);

        return new TokenController(new ResponseFactory(), $tokenService);
    }

    public function test_list_returns_200_with_tokens_array(): void
    {
        $result = $this->makeController()->list(
            new ServerRequest('GET', '/api/v1/tokens')
        );

        $this->assertSame(200, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertArrayHasKey('tokens', $body['data'] ?? $body);
    }

    public function test_create_throws_validation_exception_when_name_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeController()->create(
            new ServerRequest('POST', '/api/v1/tokens')
                ->withParsedBody(['name' => ''])
        );
    }

    public function test_create_throws_validation_exception_when_name_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeController()->create(
            new ServerRequest('POST', '/api/v1/tokens')
        );
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(TokenController::class, 'list'));
        $this->assertTrue(\method_exists(TokenController::class, 'create'));
        $this->assertTrue(\method_exists(TokenController::class, 'revoke'));
    }

    // ─────────────────────────────────────────────────────────────
    // create() — éxito
    // ─────────────────────────────────────────────────────────────

    public function test_create_returns_201_with_plain_token(): void
    {
        $tokenService = $this->createMock(ApiTokenServiceInterface::class);
        $tokenService->method('generate')->willReturn(\str_repeat('a', 64));

        $controller = new TokenController(new ResponseFactory(), $tokenService);

        $response = $controller->create(
            (new ServerRequest('POST', '/api/v1/tokens'))
                ->withParsedBody(['name' => 'My CLI token'])
                ->withAttribute('user_id', 3)
        );

        $this->assertSame(201, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('token', $body['data'] ?? []);
        $this->assertSame('My CLI token', $body['data']['name'] ?? null);
    }

    public function test_create_throws_validation_exception_when_name_too_long(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeController()->create(
            new ServerRequest('POST', '/api/v1/tokens')
                ->withParsedBody(['name' => \str_repeat('x', 101)])
        );
    }

    // ─────────────────────────────────────────────────────────────
    // revoke()
    // ─────────────────────────────────────────────────────────────

    public function test_revoke_owned_token_returns_200(): void
    {
        $tokenService = $this->createMock(ApiTokenServiceInterface::class);
        $tokenService->method('revoke')->willReturn(Result::ok(true));

        $controller = new TokenController(new ResponseFactory(), $tokenService);

        $response = $controller->revoke(
            (new ServerRequest('DELETE', '/api/v1/tokens/1'))
                ->withAttribute('id', 1)
                ->withAttribute('user_id', 5)
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['revoked'] ?? false);
    }

    public function test_revoke_foreign_token_returns_404(): void
    {
        $tokenService = $this->createMock(ApiTokenServiceInterface::class);
        $tokenService->method('revoke')->willReturn(Result::fail('Token no encontrado.', 'not_found'));

        $controller = new TokenController(new ResponseFactory(), $tokenService);

        $response = $controller->revoke(
            (new ServerRequest('DELETE', '/api/v1/tokens/99'))
                ->withAttribute('id', 99)
                ->withAttribute('user_id', 5)
        );

        $this->assertSame(404, $response->getStatusCode());
    }
}
