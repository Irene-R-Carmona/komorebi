<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ApiTokenService: generación, validación y revocación de tokens.
 * ¿Qué me quieres demostrar? Que los tokens se validan contra el hash SHA-256, que la cuenta inactiva retorna fail, y que la revocación funciona.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el algoritmo de hash, la lógica de validación de cuenta activa, o la firma de los métodos públicos.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\UserDTO;
use App\Repositories\Contracts\ApiTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\ApiTokenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiTokenService::class)]
final class ApiTokenServiceTest extends TestCase
{
    private ApiTokenRepositoryInterface $tokenRepoStub;
    private UserRepositoryInterface $userRepoStub;
    private ApiTokenService $service;

    protected function setUp(): void
    {
        $this->tokenRepoStub = $this->createStub(ApiTokenRepositoryInterface::class);
        $this->userRepoStub  = $this->createStub(UserRepositoryInterface::class);
        $this->service       = new ApiTokenService($this->tokenRepoStub, $this->userRepoStub);
    }

    public function testGenerateReturnsNonEmptyString(): void
    {
        $this->tokenRepoStub->method('createToken')->willReturn(1);

        $token = $this->service->generate(1, 'test-token');

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertSame(64, \strlen($token));
    }

    public function testGenerateReturnsDifferentTokensEachCall(): void
    {
        $token1 = $this->service->generate(1, 'token-a');
        $token2 = $this->service->generate(1, 'token-b');

        $this->assertNotSame($token1, $token2);
    }

    public function testValidateReturnsFailWhenTokenNotFound(): void
    {
        $this->tokenRepoStub->method('findByHash')->willReturn(null);

        $result = $this->service->validate('invalid-plain-token');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inválido', $result->error);
    }

    public function testValidateReturnsFailWhenUserIsInactive(): void
    {
        $this->tokenRepoStub->method('findByHash')->willReturn([
            'id' => 1,
            'user_id' => 10,
        ]);
        $this->userRepoStub->method('findById')->willReturn(new UserDTO(id: 10, uuid: '', name: 'Inactive', email: 'inactive@test.com', avatar: null, roles: [], is_active: false, cafe_id: null, created_at: ''));
        $this->userRepoStub->method('getRoles')->willReturn([]);

        $result = $this->service->validate('sometoken');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('desactivada', $result->error);
    }

    public function testValidateReturnsOkWhenTokenAndUserAreValid(): void
    {
        $this->tokenRepoStub->method('findByHash')->willReturn([
            'id' => 1,
            'user_id' => 5,
        ]);
        $this->userRepoStub->method('findById')->willReturn(new UserDTO(id: 5, uuid: '', name: 'Active', email: 'active@test.com', avatar: null, roles: [], is_active: true, cafe_id: null, created_at: ''));
        $this->userRepoStub->method('getRoles')->willReturn([['slug' => 'user']]);

        $result = $this->service->validate('sometoken');

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('user_id', $result->data);
        $this->assertSame(5, $result->data['user_id']);
    }

    public function testValidateReturnsFailWhenUserNotFound(): void
    {
        $this->tokenRepoStub->method('findByHash')->willReturn([
            'id' => 1,
            'user_id' => 99,
        ]);
        $this->userRepoStub->method('findById')->willReturn(null);

        $result = $this->service->validate('sometoken');

        $this->assertFalse($result->ok);
    }

    public function testRevokeReturnsFailWhenTokenNotFound(): void
    {
        $this->tokenRepoStub->method('revoke')->willReturn(false);

        $result = $this->service->revoke(1, 10);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testRevokeReturnsOkWhenSuccessful(): void
    {
        $this->tokenRepoStub->method('revoke')->willReturn(true);

        $result = $this->service->revoke(1, 10);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testListForUserDelegatesToRepository(): void
    {
        $expected = [['id' => 1, 'name' => 'mobile']];
        $this->tokenRepoStub->method('listForUser')->willReturn($expected);

        $result = $this->service->listForUser(5);

        $this->assertSame($expected, $result);
    }
}
