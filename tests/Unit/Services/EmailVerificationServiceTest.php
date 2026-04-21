<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * EmailVerificationService: envío de email de verificación y validación de token de email.
 *
 * ¿Qué me quieres demostrar?
 * Que el servicio rechaza el envío cuando el usuario no existe o ya tiene el email verificado,
 * que delega la generación del token en AuthTokenService, y que verifyEmailToken propaga
 * fielmente el resultado de AuthTokenService::verifyEmail.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guarda "usuario no encontrado", si se permite enviar a un usuario ya
 * verificado, si el email no llega con los datos del usuario o si la URL de verificación
 * pierde el parámetro token.
 */

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\Contracts\AuthTokenRepositoryInterface;
use App\Services\AuthTokenService;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\EmailVerificationService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailVerificationService::class)]
final class EmailVerificationServiceTest extends TestCase
{
    /** @var AuthTokenRepositoryInterface&Stub */
    private AuthTokenRepositoryInterface $authTokenRepoStub;
    /** @var EmailServiceInterface&Stub */
    private EmailServiceInterface $emailServiceStub;

    protected function setUp(): void
    {
        $this->authTokenRepoStub = $this->createStub(AuthTokenRepositoryInterface::class);
        $this->emailServiceStub  = $this->createStub(EmailServiceInterface::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un User con un PDO stub cuyo fetch() devuelve $userData.
     *
     * @param array<string, mixed>|false $userData
     */
    private function makeUser(array|false $userData = false): User
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $stmtStub->method('fetch')->willReturn($userData);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        return new User($pdoStub);
    }

    private function makeService(User $userModel): EmailVerificationService
    {
        return new EmailVerificationService(
            $userModel,
            new AuthTokenService($this->authTokenRepoStub),
            $this->emailServiceStub,
        );
    }

    // ─────────────────────────────────────────────────────────────
    // sendVerificationEmail
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna fallo cuando el usuario no existe')]
    public function testSendVerificationEmailWithNonExistentUserReturnsFailure(): void
    {
        $result = $this->makeService($this->makeUser(false))->sendVerificationEmail(999);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error ?? '');
    }

    #[TestDox('Retorna fallo cuando el email ya está verificado')]
    public function testSendVerificationEmailWhenAlreadyVerifiedReturnsFailure(): void
    {
        $this->authTokenRepoStub->method('isUserEmailVerified')->willReturn(true);

        $result = $this->makeService($this->makeUser([
            'id'    => 1,
            'email' => 'u@example.com',
            'name'  => 'Test',
        ]))->sendVerificationEmail(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('verificado', $result->error ?? '');
    }

    #[TestDox('Envía el email y retorna éxito cuando el usuario existe y no está verificado')]
    public function testSendVerificationEmailWithValidUnverifiedUserReturnsSuccess(): void
    {
        $this->authTokenRepoStub->method('isUserEmailVerified')->willReturn(false);

        $result = $this->makeService($this->makeUser([
            'id'    => 1,
            'email' => 'user@example.com',
            'name'  => 'Usuario Test',
        ]))->sendVerificationEmail(1);

        $this->assertTrue($result->ok);
    }

    #[TestDox('El email de verificación se envía con el email y nombre del usuario')]
    public function testSendVerificationEmailInvokesEmailServiceWithCorrectUserData(): void
    {
        $this->authTokenRepoStub->method('isUserEmailVerified')->willReturn(false);

        /** @var EmailServiceInterface&MockObject $emailMock */
        $emailMock = $this->createMock(EmailServiceInterface::class);
        $emailMock->expects($this->once())
            ->method('sendVerificationEmail')
            ->with(
                'user@example.com',
                'Test User',
                $this->stringContains('/auth/verify-email?token='),
            );

        $service = new EmailVerificationService(
            $this->makeUser(['id' => 1, 'email' => 'user@example.com', 'name' => 'Test User']),
            new AuthTokenService($this->authTokenRepoStub),
            $emailMock,
        );

        $service->sendVerificationEmail(1);
    }

    #[TestDox('Retorna fallo cuando el email ya está verificado aunque el servicio de email esté disponible')]
    public function testSendVerificationEmailDoesNotSendEmailWhenAlreadyVerified(): void
    {
        $this->authTokenRepoStub->method('isUserEmailVerified')->willReturn(true);

        /** @var EmailServiceInterface&MockObject $emailMock */
        $emailMock = $this->createMock(EmailServiceInterface::class);
        $emailMock->expects($this->never())->method('sendVerificationEmail');

        $service = new EmailVerificationService(
            $this->makeUser(['id' => 1, 'email' => 'u@example.com', 'name' => 'T']),
            new AuthTokenService($this->authTokenRepoStub),
            $emailMock,
        );

        $service->sendVerificationEmail(1);
    }

    // ─────────────────────────────────────────────────────────────
    // verifyEmailToken
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna éxito con user_id cuando el token de verificación es válido')]
    public function testVerifyEmailTokenWithValidTokenReturnsSuccess(): void
    {
        $this->authTokenRepoStub->method('findValidEmailVerificationToken')
            ->willReturn(['id' => 10, 'user_id' => 7]);

        $result = $this->makeService($this->makeUser())->verifyEmailToken('valid_hex_token');

        $this->assertTrue($result->ok);
        $this->assertSame(7, $result->data['user_id']);
    }

    #[TestDox('Retorna fallo cuando el token de verificación es inválido o ha expirado')]
    public function testVerifyEmailTokenWithInvalidTokenReturnsFailure(): void
    {
        $this->authTokenRepoStub->method('findValidEmailVerificationToken')
            ->willReturn(null);

        $result = $this->makeService($this->makeUser())->verifyEmailToken('expired_or_wrong_token');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('verifyEmailToken propaga exactamente el resultado de AuthTokenService sin modificarlo')]
    public function testVerifyEmailTokenDelegatesToTokenServiceWithoutTransformation(): void
    {
        $this->authTokenRepoStub->method('findValidEmailVerificationToken')
            ->willReturn(['id' => 5, 'user_id' => 42]);

        $result = $this->makeService($this->makeUser())->verifyEmailToken('some_token');

        $this->assertTrue($result->ok);
        $this->assertSame(42, $result->data['user_id']);
    }
}
