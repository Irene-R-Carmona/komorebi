<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? UserManagementService: validación de datos de usuario (nombre, email, contraseña, rol).
 * ¿Qué me quieres demostrar? Que validateUserData retorna fail con código 'validation' si algún campo es inválido.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian las reglas de validación de nombre, email, contraseña o rol.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\UserManagementRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserManagementService::class)]
final class UserManagementServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private UserManagementRepositoryInterface $userMgmtRepoStub;
    private UserManagementService $service;

    protected function setUp(): void
    {
        $this->userRepoStub     = $this->createStub(UserRepositoryInterface::class);
        $this->userMgmtRepoStub = $this->createStub(UserManagementRepositoryInterface::class);
        $this->service          = new UserManagementService($this->userRepoStub, $this->userMgmtRepoStub);
    }

    private function validUserData(): array
    {
        return [
            'name'     => 'María García',
            'email'    => 'maria@example.com',
            'password' => 'Password123',
            'role_id'  => 3,
        ];
    }

    public function testValidateUserDataSucceedsWithValidData(): void
    {
        $result = $this->service->validateUserData($this->validUserData());

        $this->assertTrue($result->ok);
    }

    public function testValidateUserDataFailsWhenNameTooShort(): void
    {
        $data         = $this->validUserData();
        $data['name'] = 'A';

        $result = $this->service->validateUserData($data);

        $this->assertFalse($result->ok);
        $this->assertSame('validation', $result->code);
    }

    public function testValidateUserDataFailsWhenEmailInvalid(): void
    {
        $data          = $this->validUserData();
        $data['email'] = 'not-an-email';

        $result = $this->service->validateUserData($data);

        $this->assertFalse($result->ok);
    }

    public function testValidateUserDataFailsWhenPasswordTooShort(): void
    {
        $data             = $this->validUserData();
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
}
