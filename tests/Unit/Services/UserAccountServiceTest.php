<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? UserAccountService: validaciones de contraseña, cambio de contraseña y desactivación de cuenta.
 * ¿Qué me quieres demostrar? Que changePassword valida longitud mínima, mayúscula, número y coincidencia; que deactivateAccount delega en el modelo User.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian las reglas de validación de contraseña o la lógica de desactivación.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserAccountService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserAccountService::class)]
final class UserAccountServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private UserAccountService $service;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
        $this->service = new UserAccountService($this->userRepoStub);
    }

    public function testChangePasswordFailsWhenPasswordsTooShort(): void
    {
        $result = $this->service->changePassword(1, 'current', 'Short1');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('8 caracteres', $result->error);
    }

    public function testChangePasswordFailsWhenNoUppercase(): void
    {
        $result = $this->service->changePassword(1, 'current', 'password123');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('mayúscula', $result->error);
    }

    public function testChangePasswordFailsWhenNoNumber(): void
    {
        $result = $this->service->changePassword(1, 'current', 'PasswordNoNum');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('número', $result->error);
    }

    public function testChangePasswordFailsWhenPasswordsDoNotMatch(): void
    {
        $result = $this->service->changePassword(1, 'current', 'Password1', 'Password2');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no coinciden', $result->error);
    }

    public function testChangePasswordFailsWhenCurrentPasswordWrong(): void
    {
        // userModel->findById devuelve null (PDO stub sin datos), fallback al repo
        $this->userRepoStub->method('findByIdForSecurity')->willReturn(['id' => 1, 'password' => 'invalid_hash']);

        $result = $this->service->changePassword(1, 'wrongcurrent', 'NewPassword1');

        $this->assertFalse($result->ok);
    }

    public function testChangePasswordSucceeds(): void
    {
        // userModel->findById devuelve null (PDO stub sin datos), fallback al repo con hash real
        $hash = \password_hash('correctpassword', PASSWORD_DEFAULT);
        $this->userRepoStub->method('findByIdForSecurity')->willReturn(['id' => 1, 'password' => $hash]);

        $result = $this->service->changePassword(1, 'correctpassword', 'NewPassword1');

        $this->assertTrue($result->ok);
    }

    public function testDeactivateAccountSucceeds(): void
    {
        $this->userRepoStub->method('setActive')->willReturn(true);

        $result = $this->service->deactivateAccount(1);

        $this->assertTrue($result->ok);
    }

    public function testDeactivateAccountFailsWhenModelFails(): void
    {
        $this->userRepoStub->method('setActive')->willReturn(false);

        $result = $this->service->deactivateAccount(1);

        $this->assertFalse($result->ok);
    }
}
