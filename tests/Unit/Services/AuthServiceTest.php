<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AuthService: validaciones de login (campos vacíos) y register (nombre, email, contraseña, coincidencia, duplicado).
 * ¿Qué me quieres demostrar? Que las validaciones de entrada en login y register retornan Result::fail antes de tocar la BD.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan o cambian las validaciones de formato/contenido.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuthService;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthService::class)]
final class AuthServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private SessionManagementServiceInterface $sessionStub;
    private RateLimitingServiceInterface $rateLimiterStub;
    private AuthService $service;

    protected function setUp(): void
    {
        $this->userRepoStub    = $this->createStub(UserRepositoryInterface::class);
        $this->sessionStub     = $this->createStub(SessionManagementServiceInterface::class);
        $this->rateLimiterStub = $this->createStub(RateLimitingServiceInterface::class);

        $this->rateLimiterStub->method('isBlocked')->willReturn(['blocked' => false]);

        $this->service = new AuthService(
            $this->userRepoStub,
            $this->sessionStub,
            $this->rateLimiterStub
        );
    }

    public function testLoginFailsWhenEmailEmpty(): void
    {
        $result = $this->service->login('', 'password');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('requeridos', $result->error);
    }

    public function testLoginFailsWhenPasswordEmpty(): void
    {
        $result = $this->service->login('test@example.com', '');

        $this->assertFalse($result->ok);
    }

    public function testRegisterFailsWhenNameEmpty(): void
    {
        $result = $this->service->register('', 'a@a.com', 'Password1', 'Password1');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Nombre inválido', $result->error);
    }

    public function testRegisterFailsWhenEmailInvalid(): void
    {
        $result = $this->service->register('María', 'not-email', 'Password1', 'Password1');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Email inválido', $result->error);
    }

    public function testRegisterFailsWhenPasswordTooShort(): void
    {
        $result = $this->service->register('María', 'a@a.com', 'short', 'short');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('8 caracteres', $result->error);
    }

    public function testRegisterFailsWhenPasswordsDoNotMatch(): void
    {
        $result = $this->service->register('María', 'a@a.com', 'Password1', 'Password2');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no coinciden', $result->error);
    }

    public function testRegisterFailsWhenEmailAlreadyExists(): void
    {
        $this->userRepoStub->method('emailExists')->willReturn(true);

        $result = $this->service->register('María', 'existing@a.com', 'Password1', 'Password1');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('registrado', $result->error);
    }

    public function testLoginFailsWhenUserNotFound(): void
    {
        $this->userRepoStub->method('findByEmailWithCredentials')->willReturn(null);

        $result = $this->service->login('noexiste@example.com', 'password');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Credenciales', $result->error);
    }

    public function testLoginFailsWhenAccountIsLocked(): void
    {
        $user = ['id' => 1, 'email' => 'test@example.com', 'is_active' => true, 'failed_attempts' => 5];
        $this->userRepoStub->method('findByEmailWithCredentials')->willReturn($user);
        $this->userRepoStub->method('isLocked')->willReturn(true);
        $this->userRepoStub->method('lockoutMinutesRemaining')->willReturn(10);

        $result = $this->service->login('test@example.com', 'password');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('bloqueada', $result->error);
    }

    public function testLoginFailsWhenAccountIsDeactivated(): void
    {
        $user = ['id' => 1, 'email' => 'test@example.com', 'is_active' => false, 'failed_attempts' => 0];
        $this->userRepoStub->method('findByEmailWithCredentials')->willReturn($user);
        $this->userRepoStub->method('isLocked')->willReturn(false);

        $result = $this->service->login('test@example.com', 'password');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('desactivada', $result->error);
    }

    public function testLoginFailsWhenPasswordIsWrong(): void
    {
        $user = ['id' => 1, 'email' => 'test@example.com', 'is_active' => true, 'failed_attempts' => 0];
        $this->userRepoStub->method('findByEmailWithCredentials')->willReturn($user);
        $this->userRepoStub->method('isLocked')->willReturn(false);
        $this->userRepoStub->method('verifyPassword')->willReturn(false);

        $result = $this->service->login('test@example.com', 'wrong-password');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Credenciales', $result->error);
    }

    public function testCheckReturnsFalseWhenNotAuthenticated(): void
    {
        $result = $this->service->check();

        $this->assertFalse($result);
    }

    public function testUserReturnsNullWhenNotAuthenticated(): void
    {
        $result = $this->service->user();

        $this->assertNull($result);
    }
}
