<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use PHPUnit\Framework\TestCase;

/**
 * Tests para UserService
 *
 * Verifica:
 * - Gestión de perfiles de usuario
 * - Actualización de datos
 * - Cambio de contraseña
 * - Verificación de email
 */
final class UserServiceTest extends TestCase
{
    private UserService $service;
    private UserRepository $repoMock;
    private User $userModelMock;

    protected function setUp(): void
    {
        $this->repoMock = $this->createStub(UserRepository::class);
        $this->userModelMock = $this->createStub(User::class);
        $this->service = new UserService($this->repoMock, $this->userModelMock);
    }

    public function testGetProfileReturnsUserData(): void
    {
        $expectedUser = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_active' => true
        ];

        $this->repoMock->method('findById')->willReturn($expectedUser);
        $this->repoMock->method('getRoles')->willReturn([
            ['slug' => 'user']
        ]);

        $profile = $this->service->getProfile(1);

        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);
    }

    public function testGetProfileWithInvalidUserThrowsException(): void
    {
        $this->repoMock->method('findById')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->getProfile(999);
    }

    public function testUpdateProfileReturnsSuccess(): void
    {
        $updateData = [
            'name' => 'Updated Name',
            'phone' => '123456789'
        ];

        $this->repoMock->method('update')->willReturn(true);

        $result = $this->service->updateProfile(1, $updateData);

        $this->assertTrue($result->ok);
    }

    public function testUpdateProfileWithInvalidDataReturnsError(): void
    {
        $updateData = [
            'name' => '', // Nombre vacío
        ];

        $result = $this->service->updateProfile(1, $updateData);

        $this->assertFalse($result->ok);
    }

    public function testChangePasswordWithCorrectOldPasswordReturnsSuccess(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'password_hash' => password_hash('oldpassword', PASSWORD_ARGON2ID)
        ]);

        $this->userModelMock->method('updatePassword')->willReturn(true);

        $result = $this->service->changePassword(1, 'oldpassword', 'newpassword123');

        $this->assertTrue($result->ok);
    }

    public function testChangePasswordWithIncorrectOldPasswordReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'password_hash' => password_hash('oldpassword', PASSWORD_ARGON2ID)
        ]);

        $result = $this->service->changePassword(1, 'wrongpassword', 'newpassword123');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('incorrecta', strtolower($result->error ?? ''));
    }

    public function testChangePasswordWithWeakNewPasswordReturnsError(): void
    {
        $this->userModelMock->method('findById')->willReturn([
            'id' => 1,
            'password_hash' => password_hash('oldpassword', PASSWORD_ARGON2ID)
        ]);

        $result = $this->service->changePassword(1, 'oldpassword', '123'); // Muy corta

        $this->assertFalse($result->ok);
    }

    public function testVerifyEmailReturnsSuccess(): void
    {
        $this->userModelMock->method('verifyEmail')->willReturn(true);

        $result = $this->service->verifyEmail(1);

        $this->assertTrue($result->ok);
    }

    public function testDeactivateAccountSetsInactive(): void
    {
        $this->userModelMock->method('setActive')->willReturn(true);

        $result = $this->service->deactivateAccount(1);

        $this->assertTrue($result->ok);
    }

    public function testReactivateAccountSetsActive(): void
    {
        $this->userModelMock->method('setActive')->willReturn(true);

        $result = $this->service->reactivateAccount(1);

        $this->assertTrue($result->ok);
    }

    public function testGetUsersByRoleReturnsArray(): void
    {
        $expectedUsers = [
            ['id' => 1, 'name' => 'Admin 1'],
            ['id' => 2, 'name' => 'Admin 2']
        ];

        $this->repoMock->method('findByRole')->willReturn($expectedUsers);

        $users = $this->service->getUsersByRole('admin');

        $this->assertIsArray($users);
        $this->assertCount(2, $users);
    }

    public function testHasPermissionReturnsTrueWhenUserHasPermission(): void
    {
        $this->repoMock->method('hasPermission')->willReturn(true);

        $hasPermission = $this->service->hasPermission(1, 'review.create');

        $this->assertTrue($hasPermission);
    }

    public function testHasPermissionReturnsFalseWhenUserLacksPermission(): void
    {
        $this->repoMock->method('hasPermission')->willReturn(false);

        $hasPermission = $this->service->hasPermission(1, 'admin.access');

        $this->assertFalse($hasPermission);
    }
}
