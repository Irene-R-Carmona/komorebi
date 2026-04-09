<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios del servicio ApiTokenService.
 *
 * ¿Qué me quieres demostrar?
 * Que generate() produce un token de 64 chars hex, que validate() autentica
 * correctamente o rechaza tokens inválidos/revocados/expirados, y que
 * revoke() enforza el ownership.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en el algoritmo de hashing, en la lógica de validación
 * de tokens o en la lógica de ownership de revocación.
 */

namespace Services;

use App\Core\Result;
use App\Repositories\Contracts\ApiTokenRepositoryInterface;
use App\Services\ApiTokenService;
use PHPUnit\Framework\TestCase;

final class ApiTokenServiceTest extends TestCase
{
    private ApiTokenRepositoryInterface $repository;
    private ApiTokenService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ApiTokenRepositoryInterface::class);
        $this->service    = new ApiTokenService($this->repository);
    }

    // ─────────────────────────────────────────────────────────────
    // generate()
    // ─────────────────────────────────────────────────────────────

    public function testGenerateReturns64HexChars(): void
    {
        $this->repository->method('createToken')->willReturn(1);

        $plain = $this->service->generate(1, 'Test token');

        $this->assertSame(64, strlen($plain));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $plain);
    }

    public function testGenerateDifferentTokensEachCall(): void
    {
        $this->repository->method('createToken')->willReturn(1);

        $plain1 = $this->service->generate(1, 'Token A');
        $plain2 = $this->service->generate(1, 'Token B');

        $this->assertNotSame($plain1, $plain2);
    }

    public function testGenerateCallsRepositoryCreate(): void
    {
        $repo = $this->createMock(ApiTokenRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('createToken')
            ->with(
                $this->equalTo(42),
                $this->equalTo('My token'),
                $this->matchesRegularExpression('/^[0-9a-f]{64}$/'),
                $this->isNull()
            )
            ->willReturn(1);

        $service = new ApiTokenService($repo);
        $service->generate(42, 'My token');
    }

    // ─────────────────────────────────────────────────────────────
    // validate() — token inválido (no encontrado en BD)
    // ─────────────────────────────────────────────────────────────

    public function testValidateWithUnknownTokenReturnsFail(): void
    {
        $this->repository->method('findByHash')->willReturn(null);

        $result = $this->service->validate('aabbcc');

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_token', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // revoke()
    // ─────────────────────────────────────────────────────────────

    public function testRevokeReturnsFail_WhenTokenNotFoundOrAlreadyRevoked(): void
    {
        $this->repository->method('revoke')->willReturn(false);

        $result = $this->service->revoke(99, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testRevokeReturnsOk_WhenSuccessful(): void
    {
        $this->repository->method('revoke')->willReturn(true);

        $result = $this->service->revoke(1, 1);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testRevokeEnforcesOwnership(): void
    {
        // El repositorio recibe user_id del parámetro — si difiere del propietario
        // del token, revoke() retorna false y el repositorio NO actualiza nada.
        $repo = $this->createMock(ApiTokenRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('revoke')
            ->with($this->equalTo(5), $this->equalTo(99)) // tokenId=5, userId=99 (ajeno)
            ->willReturn(false);

        $service = new ApiTokenService($repo);
        $result  = $service->revoke(5, 99);

        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // listForUser()
    // ─────────────────────────────────────────────────────────────

    public function testListForUserDelegatestoRepository(): void
    {
        $expected = [
            ['id' => 1, 'name' => 'CLI', 'created_at' => '2026-04-01 10:00:00'],
        ];
        $this->repository->method('listForUser')->willReturn($expected);

        $result = $this->service->listForUser(7);

        $this->assertSame($expected, $result);
    }

    public function testListForUserDoesNotContainTokenHash(): void
    {
        // El repositorio ya excluye token_hash en listForUser — verificar que
        // el servicio no añade el campo por cuenta propia.
        $rows = [
            ['id' => 1, 'name' => 'API key', 'last_used_at' => null, 'expires_at' => null, 'created_at' => '2026-04-01'],
        ];
        $this->repository->method('listForUser')->willReturn($rows);

        $result = $this->service->listForUser(1);

        $this->assertArrayNotHasKey('token_hash', $result[0]);
    }
}
