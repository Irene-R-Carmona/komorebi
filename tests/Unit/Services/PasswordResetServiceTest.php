<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? PasswordResetService: validaciones de nueva contraseña al resetear y flujo de solicitud.
 * ¿Qué me quieres demostrar? Que resetPasswordWithToken retorna fail si la contraseña es corta o no coinciden,
 *   y que requestPasswordReset responde ok sin revelar si el email existe.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las validaciones de contraseña o la
 *   respuesta genérica por seguridad.
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthTokenServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use App\Services\PasswordResetService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetService::class)]
final class PasswordResetServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private AuthTokenServiceInterface $tokenServiceStub;
    private SessionManagementServiceInterface $sessionStub;
    private RateLimitingServiceInterface $rateLimiterStub;
    private EmailServiceInterface $emailServiceStub;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        $this->userRepoStub    = $this->createStub(UserRepositoryInterface::class);
        $this->tokenServiceStub = $this->createStub(AuthTokenServiceInterface::class);
        $this->sessionStub      = $this->createStub(SessionManagementServiceInterface::class);
        $this->rateLimiterStub  = $this->createStub(RateLimitingServiceInterface::class);
        $this->emailServiceStub = $this->createStub(EmailServiceInterface::class);

        $this->service = new PasswordResetService(
            $this->userRepoStub,
            $this->tokenServiceStub,
            $this->sessionStub,
            $this->rateLimiterStub,
            $this->emailServiceStub
        );
    }

    public function testResetPasswordWithTokenFailsWhenTokenInvalid(): void
    {
        $this->tokenServiceStub->method('validatePasswordResetToken')
            ->willReturn(Result::fail('Token inválido'));

        $result = $this->service->resetPasswordWithToken('bad-token', 'Password1', 'Password1');

        $this->assertFalse($result->ok);
    }

    public function testResetPasswordWithTokenFailsWhenPasswordTooShort(): void
    {
        $this->tokenServiceStub->method('validatePasswordResetToken')
            ->willReturn(Result::ok(['user_id' => 1]));

        $result = $this->service->resetPasswordWithToken('valid-token', 'short', 'short');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('8 caracteres', $result->error);
    }

    public function testResetPasswordWithTokenFailsWhenPasswordsDoNotMatch(): void
    {
        $this->tokenServiceStub->method('validatePasswordResetToken')
            ->willReturn(Result::ok(['user_id' => 1]));

        $result = $this->service->resetPasswordWithToken('valid-token', 'Password1', 'Password2');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no coinciden', $result->error);
    }

    public function testRequestPasswordResetReturnsOkEvenWhenEmailNotFound(): void
    {
        $this->rateLimiterStub->method('isBlocked')->willReturn(['blocked' => false]);
        $this->userRepoStub->method('findByEmail')->willReturn(null);

        $result = $this->service->requestPasswordReset('unknown@example.com', '127.0.0.1');

        $this->assertTrue($result->ok);
    }

    public function testValidatePasswordResetTokenDelegatesToTokenService(): void
    {
        $this->tokenServiceStub->method('validatePasswordResetToken')
            ->willReturn(Result::fail('Token expirado'));

        $result = $this->service->validatePasswordResetToken('some-token');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Token expirado', $result->error);
    }

    public function testResetPasswordWithTokenSucceedsWithValidInput(): void
    {
        $this->tokenServiceStub->method('validatePasswordResetToken')
            ->willReturn(Result::ok(['user_id' => 42]));
        $this->tokenServiceStub->method('consumePasswordResetToken')->willReturn(true);
        $this->userRepoStub->method('updatePassword')->willReturn(true);

        $result = $this->service->resetPasswordWithToken('valid-token', 'NewPassword1!', 'NewPassword1!');

        $this->assertTrue($result->ok);
    }

    public function testRequestPasswordResetFailsWhenRateLimited(): void
    {
        $this->rateLimiterStub->method('isBlocked')
            ->willReturn(['blocked' => true, 'minutes_remaining' => 5]);

        $result = $this->service->requestPasswordReset('user@example.com', '127.0.0.1');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Demasiados intentos', $result->error);
    }
}
