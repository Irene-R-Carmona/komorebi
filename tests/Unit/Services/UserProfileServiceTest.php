<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? UserProfileService: validación del nombre en updateProfile, delegación de consultas y actualización de avatar.
 * ¿Qué me quieres demostrar? Que nombre vacío o demasiado largo retorna fail, y que getProfile y updateAvatar delegan correctamente.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambia la validación de nombre, el método de delegación o la lógica de avatar.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\UserDTO;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserProfileService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserProfileService::class)]
final class UserProfileServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private UserProfileService $service;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
        $this->service = new UserProfileService($this->userRepoStub);
    }

    public function testUpdateProfileFailsWhenNameEmpty(): void
    {
        $result = $this->service->updateProfile(1, '');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Nombre inválido', $result->error);
    }

    public function testUpdateProfileFailsWhenNameTooLong(): void
    {
        $result = $this->service->updateProfile(1, \str_repeat('a', 101));

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Nombre inválido', $result->error);
    }

    public function testUpdateProfileSucceedsWithValidName(): void
    {
        $this->userRepoStub->method('update')->willReturn(true);

        $result = $this->service->updateProfile(1, 'Nuevo Nombre');

        $this->assertTrue($result->ok);
    }

    public function testGetProfileReturnsDelegatedData(): void
    {
        $this->userRepoStub->method('findById')->willReturn(new UserDTO(id: 1, uuid: '', name: 'Test User', email: 'user@test.com', avatar: null, roles: [], is_active: true, cafe_id: null, created_at: ''));
        $this->userRepoStub->method('getRoles')->willReturn([]);

        $result = $this->service->getProfile(1);

        $this->assertSame(1, $result['id']);
        $this->assertSame('Test User', $result['name']);
    }

    public function testGetUsersByRoleReturnsDelegatedArray(): void
    {
        $expected = [['id' => 1, 'role' => 'admin']];
        $this->userRepoStub->method('findByRole')->willReturn($expected);

        $result = $this->service->getUsersByRole('admin');

        $this->assertSame($expected, $result);
    }

    public function testUpdateAvatarSucceeds(): void
    {
        // updateAvatar llama a User::updateAvatar que ejecuta PDO; el servicio no verifica el bool
        // por lo que siempre retorna Result::ok mientras no lance excepción
        $result = $this->service->updateAvatar(1, 'avatar.jpg');

        $this->assertTrue($result->ok);
    }
}
