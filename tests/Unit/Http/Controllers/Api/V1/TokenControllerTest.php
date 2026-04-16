<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Api/V1/TokenController.
 *
 * ¿Qué me quieres demostrar?
 * Que create() lanza ValidationException cuando el campo "name" está vacío,
 * y que list() devuelve 200 con el stub del servicio de tokens.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación del campo "name" en create(),
 * o si el contrato de ApiTokenServiceInterface cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\V1\TokenController;
use App\Services\Contracts\ApiTokenServiceInterface;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;

final class TokenControllerTest extends ControllerTestCase
{
    private function makeController(): TokenController
    {
        $tokenService = $this->createStub(ApiTokenServiceInterface::class);
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
}
