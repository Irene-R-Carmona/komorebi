<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? UserManagementService: validación de datos de usuario (nombre, email, contraseña, rol).
 * ¿Qué me quieres demostrar? Que validateUserData retorna fail con código 'validation' si algún campo es inválido.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian las reglas de validación de nombre, email, contraseña o rol.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\UserDTO;
use App\Repositories\Contracts\UserManagementRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UserManagementService::class)]
final class UserManagementServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private UserManagementRepositoryInterface $userMgmtRepoStub;
    private UserManagementService $service;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
        $this->userMgmtRepoStub = $this->createStub(UserManagementRepositoryInterface::class);
        $this->service = new UserManagementService($this->userRepoStub, $this->userMgmtRepoStub);
    }

    private function validUserData(): array
    {
        return [
            'name' => 'María García',
            'email' => 'maria@example.com',
            'password' => 'Password123',
            'role_id' => 3,
        ];
    }

    public function testValidateUserDataSucceedsWithValidData(): void
    {
        $result = $this->service->validateUserData($this->validUserData());

        $this->assertTrue($result->ok);
    }

    public function testValidateUserDataFailsWhenNameTooShort(): void
    {
        $data = $this->validUserData();
        $data['name'] = 'A';

        $result = $this->service->validateUserData($data);

        $this->assertFalse($result->ok);
        $this->assertSame('validation', $result->code);
    }

    public function testValidateUserDataFailsWhenEmailInvalid(): void
    {
        $data = $this->validUserData();
        $data['email'] = 'not-an-email';

        $result = $this->service->validateUserData($data);

        $this->assertFalse($result->ok);
    }

    public function testValidateUserDataFailsWhenPasswordTooShort(): void
    {
        $data = $this->validUserData();
        $data['password'] = 'abc';

        $result = $this->service->validateUserData($data);

        $this->assertFalse($result->ok);
    }

    public function testValidateUserDataFailsWhenRoleMissing(): void
    {
        $data = $this->validUserData();
        unset($data['role_id']);

        $result = $this->service->validateUserData($data);

        $this->assertFalse($result->ok);
    }

    public function testGetUsersWithRolesReturnsArray(): void
    {
        $this->userMgmtRepoStub->method('getUsersWithRoles')->willReturn([]);

        $result = $this->service->getUsersWithRoles();

        $this->assertIsArray($result);
    }

    public function testValidateUserDataSucceedsOnUpdateWithEmptyPassword(): void
    {
        $data = ['name' => 'María García', 'email' => 'maria@example.com', 'role_id' => 3];

        $result = $this->service->validateUserData($data, true);

        $this->assertTrue($result->ok);
    }

    public function testValidateUserDataFailsWhenNameTooLong(): void
    {
        $data = $this->validUserData();
        $data['name'] = \str_repeat('a', 101);

        $result = $this->service->validateUserData($data);

        $this->assertFalse($result->ok);
    }

    public function testDeactivateUserReturnsOkResult(): void
    {
        $result = $this->service->deactivateUser(1);

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('ok', $result->data);
    }

    public function testToggleUserStatusReturnsFailWhenUserNotFound(): void
    {
        $this->userRepoStub->method('findById')->willReturn(null);

        $result = $this->service->toggleUserStatus(999);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testToggleUserStatusReturnsOkWhenUserFound(): void
    {
        $userDto = new UserDTO(1, 'uuid-test', 'Test User', 'test@example.com', null, [], true, null, '2024-01-01');
        $this->userRepoStub->method('findById')->willReturn($userDto);

        $result = $this->service->toggleUserStatus(1);

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('is_active', $result->data);
    }

    public function testGetUsersWithRolesReturnsEmptyArrayOnException(): void
    {
        $this->userMgmtRepoStub->method('getUsersWithRoles')
            ->willThrowException(new RuntimeException('DB error'));

        $result = $this->service->getUsersWithRoles();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCreateUserFailsWhenValidationFails(): void
    {
        $result = $this->service->createUser([
            'name' => 'A',
            'email' => 'not-an-email',
            'password' => 'short',
            'role_id' => null,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation', $result->code);
    }

    public function testCreateUserFailsWhenEmailAlreadyExists(): void
    {
        $this->userRepoStub->method('findByEmail')->willReturn(['id' => 99, 'email' => 'maria@example.com']);

        $result = $this->service->createUser($this->validUserData());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('registrado', $result->error);
    }

    public function testUpdateUserFailsWhenValidationFails(): void
    {
        $result = $this->service->updateUser(1, [
            'name' => 'A',
            'email' => 'not-an-email',
            'role_id' => null,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation', $result->code);
    }

    public function testUpdateUserFailsWhenUserNotFound(): void
    {
        $this->userRepoStub->method('findById')->willReturn(null);

        $result = $this->service->updateUser(999, [
            'name' => 'María García',
            'email' => 'maria@example.com',
            'role_id' => 3,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testUpdateUserFailsWhenEmailAlreadyTakenByAnotherUser(): void
    {
        $currentUser = new UserDTO(1, 'uuid-1', 'María García', 'maria@example.com', null, [], true, null, '2024-01-01');
        $this->userRepoStub->method('findById')->willReturn($currentUser);
        $this->userRepoStub->method('findByEmail')->willReturn(['id' => 2, 'email' => 'otro@example.com']);

        $result = $this->service->updateUser(1, [
            'name' => 'María García',
            'email' => 'otro@example.com',
            'role_id' => 3,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('registrado', $result->error);
    }

    public function testDeactivateUserReturnsFailWhenExceptionThrown(): void
    {
        $this->userRepoStub->method('setActive')
            ->willThrowException(new RuntimeException('DB Error'));

        $result = $this->service->deactivateUser(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }
}
