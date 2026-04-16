<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios del controlador TokenController.
 *
 * ¿Qué me quieres demostrar?
 * Que list() devuelve los tokens del usuario, create() genera un token y
 * retorna 201 con el plain text UNA sola vez, y revoke() enforza el
 * ownership devolviendo 200 o 404 según el resultado del servicio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en los códigos HTTP de respuesta, en los campos del JSON
 * de respuesta, o en las reglas de validación de nombre de token.
 */

namespace Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Api\V1\TokenController;
use App\Services\Contracts\ApiTokenServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests para Api\V1\TokenController
 */
final class TokenControllerTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\Stub&ApiTokenServiceInterface */
    private ApiTokenServiceInterface $tokenService;
    private ResponseFactory $responseFactory;
    /** @var \PHPUnit\Framework\MockObject\Stub&ServerRequestInterface */
    private ServerRequestInterface $request;
    private TokenController $controller;

    protected function setUp(): void
    {
        $this->tokenService = $this->createStub(ApiTokenServiceInterface::class);
        $this->responseFactory = new ResponseFactory();
        $this->request = $this->createStub(ServerRequestInterface::class);

        $this->controller = new TokenController(
            $this->responseFactory,
            $this->tokenService,
        );
    }

    protected function tearDown(): void
    {
        unset($this->controller, $this->tokenService, $this->request);
    }

    // ─────────────────────────────────────────────────────────────
    // list()
    // ─────────────────────────────────────────────────────────────

    public function testListReturnsTokensForUser(): void
    {
        $tokens = [
            ['id' => 1, 'name' => 'CLI', 'last_used_at' => null, 'expires_at' => null, 'created_at' => '2026-04-01'],
        ];

        $this->request->method('getAttribute')
            ->willReturn(5);

        $this->tokenService->method('listForUser')
            ->willReturn($tokens);

        $response = $this->controller->list($this->request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertSame($tokens, $body['data']['tokens'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────
    // create()
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturns201WithPlainToken(): void
    {
        $this->request->method('getParsedBody')->willReturn(['name' => 'My CLI token']);
        $this->request->method('getAttribute')
            ->willReturn(3);

        $this->tokenService->method('generate')
            ->willReturn(\str_repeat('a', 64));

        $response = $this->controller->create($this->request);

        $this->assertSame(201, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('token', $body['data'] ?? []);
        $this->assertSame('My CLI token', $body['data']['name'] ?? null);
    }

    public function testCreateWithEmptyNameThrowsValidationException(): void
    {
        $this->request->method('getParsedBody')->willReturn(['name' => '']);
        $this->request->method('getAttribute')->willReturn(1);

        $this->expectException(ValidationException::class);

        $this->controller->create($this->request);
    }

    public function testCreateWithMissingNameThrowsValidationException(): void
    {
        $this->request->method('getParsedBody')->willReturn([]);
        $this->request->method('getAttribute')->willReturn(1);

        $this->expectException(ValidationException::class);

        $this->controller->create($this->request);
    }

    public function testCreateWithTooLongNameThrowsValidationException(): void
    {
        $this->request->method('getParsedBody')->willReturn(['name' => \str_repeat('x', 101)]);
        $this->request->method('getAttribute')->willReturn(1);

        $this->expectException(ValidationException::class);

        $this->controller->create($this->request);
    }

    // ─────────────────────────────────────────────────────────────
    // revoke()
    // ─────────────────────────────────────────────────────────────

    public function testRevokeOwnedTokenReturns200(): void
    {
        $this->request->method('getAttribute')
            ->willReturnMap([
                ['id',      null, 1],
                ['user_id', null, 5],
            ]);

        $this->tokenService->method('revoke')
            ->willReturn(Result::ok(true));

        $response = $this->controller->revoke($this->request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['revoked'] ?? false);
    }

    public function testRevokeForeignTokenReturns404(): void
    {
        $this->request->method('getAttribute')
            ->willReturnMap([
                ['id',      null, 99],
                ['user_id', null, 5],
            ]);

        $this->tokenService->method('revoke')
            ->willReturn(Result::fail('Token no encontrado.', 'not_found'));

        $response = $this->controller->revoke($this->request);

        $this->assertSame(404, $response->getStatusCode());
    }
}
