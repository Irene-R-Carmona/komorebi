<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? EmailVerificationService: envío de email de verificación cuando el usuario no existe o ya verificó.
 * ¿Qué me quieres demostrar? Que sendVerificationEmail retorna fail si el usuario no existe o ya está verificado.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambia la guarda de usuario no encontrado o ya verificado.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\UserDTO;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthTokenServiceInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\EmailVerificationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailVerificationService::class)]
final class EmailVerificationServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private AuthTokenServiceInterface $tokenServiceStub;
    private EmailServiceInterface $emailServiceStub;
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        $this->userRepoStub    = $this->createStub(UserRepositoryInterface::class);
        $this->tokenServiceStub = $this->createStub(AuthTokenServiceInterface::class);
        $this->emailServiceStub = $this->createStub(EmailServiceInterface::class);

        $this->service = new EmailVerificationService(
            $this->userRepoStub,
            $this->tokenServiceStub,
            $this->emailServiceStub
        );
    }

    public function testSendVerificationEmailFailsWhenUserNotFound(): void
    {
        $this->userRepoStub->method('findById')->willReturn(null);

        $result = $this->service->sendVerificationEmail(999);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testSendVerificationEmailFailsWhenAlreadyVerified(): void
    {
        $userDto = new UserDTO(1, 'uuid-1', 'Test', 'a@b.com', null, [], true, null, '2024-01-01');
        $this->userRepoStub->method('findById')->willReturn($userDto);
        $this->tokenServiceStub->method('isEmailVerified')->willReturn(true);

        $result = $this->service->sendVerificationEmail(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('ya verificado', $result->error);
    }

    public function testSendVerificationEmailSucceeds(): void
    {
        $userDto = new UserDTO(1, 'uuid-1', 'Test', 'a@b.com', null, [], true, null, '2024-01-01');
        $this->userRepoStub->method('findById')->willReturn($userDto);
        $this->tokenServiceStub->method('isEmailVerified')->willReturn(false);
        $this->tokenServiceStub->method('createEmailVerificationToken')->willReturn('token123');

        $result = $this->service->sendVerificationEmail(1);

        $this->assertTrue($result->ok);
    }
}
