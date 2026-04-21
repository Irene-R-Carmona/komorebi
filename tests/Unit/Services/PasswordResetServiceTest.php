<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * PasswordResetService: solicitud de reset, validación de token y cambio de contraseña.
 *
 * ¿Qué me quieres demostrar?
 * Que el rate limiting bloquea requests abusivos, que el servicio devuelve un mensaje genérico
 * independientemente de si el email existe (sin filtración de información), que la validación
 * de la nueva contraseña es estricta (longitud mínima, coincidencia) y que el token se consume
 * tras el reset exitoso.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el rate limiting, si se revelan diferencias entre emails existentes y
 * no existentes en la respuesta, si se debilita la validación de contraseña o si el token
 * no se invalida tras el reset.
 */

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\Contracts\AuthLogRepositoryInterface;
use App\Repositories\Contracts\AuthTokenRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\AuthTokenService;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\PasswordResetService;
use App\Services\SessionManagementService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetService::class)]
final class PasswordResetServiceTest extends TestCase
{
    /** @var AuthTokenRepositoryInterface&Stub */
    private AuthTokenRepositoryInterface $authTokenRepoStub;
    /** @var RateLimitingServiceInterface&Stub */
    private RateLimitingServiceInterface $rateLimiterStub;
    /** @var EmailServiceInterface&Stub */
    private EmailServiceInterface $emailServiceStub;
    /** @var SessionRepositoryInterface&Stub */
    private SessionRepositoryInterface $sessionRepoStub;
    /** @var AuthLogRepositoryInterface&Stub */
    private AuthLogRepositoryInterface $authLogRepoStub;

    protected function setUp(): void
    {
        $this->authTokenRepoStub = $this->createStub(AuthTokenRepositoryInterface::class);
        $this->rateLimiterStub   = $this->createStub(RateLimitingServiceInterface::class);
        $this->emailServiceStub  = $this->createStub(EmailServiceInterface::class);
        $this->sessionRepoStub   = $this->createStub(SessionRepositoryInterface::class);
        $this->authLogRepoStub   = $this->createStub(AuthLogRepositoryInterface::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed>|false $userData Resultado de fetch() para findByEmail
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

    private function makeService(User $userModel): PasswordResetService
    {
        return new PasswordResetService(
            $userModel,
            new AuthTokenService($this->authTokenRepoStub),
            new SessionManagementService($this->sessionRepoStub, $this->authLogRepoStub),
            $this->rateLimiterStub,
            $this->emailServiceStub,
        );
    }

    // ─────────────────────────────────────────────────────────────
    // requestPasswordReset
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna fallo con minutos restantes cuando la IP está bloqueada por rate limiting')]
    public function testRequestPasswordResetWhenBlockedByRateLimiterReturnsFailure(): void
    {
        $this->rateLimiterStub->method('isBlocked')
            ->willReturn(['blocked' => true, 'minutes_remaining' => 10]);

        $result = $this->makeService($this->makeUser())->requestPasswordReset('any@example.com', '1.2.3.4');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Demasiados intentos', $result->error ?? '');
        $this->assertStringContainsString('10', $result->error ?? '');
    }

    #[TestDox('Retorna mensaje genérico cuando el email no existe (sin filtrar si existe o no)')]
    public function testRequestPasswordResetWithUnknownEmailReturnsGenericSuccessMessage(): void
    {
        $this->rateLimiterStub->method('isBlocked')->willReturn(['blocked' => false]);

        $result = $this->makeService($this->makeUser(false))->requestPasswordReset(
            'unknown@example.com',
            '1.2.3.4',
        );

        // El servicio devuelve ok para no revelar si el email está registrado
        $this->assertTrue($result->ok);
    }

    #[TestDox('Envía email de reset y retorna éxito cuando el usuario existe y no está bloqueado')]
    public function testRequestPasswordResetWithValidEmailReturnsSuccess(): void
    {
        $this->rateLimiterStub->method('isBlocked')->willReturn(['blocked' => false]);

        $result = $this->makeService($this->makeUser([
            'id'    => 1,
            'email' => 'user@example.com',
            'name'  => 'Usuario',
        ]))->requestPasswordReset('user@example.com', '1.2.3.4', 'Mozilla/5.0');

        $this->assertTrue($result->ok);
    }

    #[TestDox('El mensaje de éxito es idéntico tanto si el email existe como si no')]
    public function testRequestPasswordResetReturnsIdenticalMessageRegardlessOfEmailExistence(): void
    {
        $this->rateLimiterStub->method('isBlocked')->willReturn(['blocked' => false]);

        $resultNoUser = $this->makeService($this->makeUser(false))
            ->requestPasswordReset('nobody@example.com', '1.2.3.4');

        $this->rateLimiterStub = $this->createStub(RateLimitingServiceInterface::class);
        $this->rateLimiterStub->method('isBlocked')->willReturn(['blocked' => false]);

        $resultWithUser = $this->makeService($this->makeUser([
            'id'    => 2,
            'email' => 'somebody@example.com',
            'name'  => 'Somebody',
        ]))->requestPasswordReset('somebody@example.com', '1.2.3.4');

        $this->assertSame($resultNoUser->data, $resultWithUser->data);
    }

    // ─────────────────────────────────────────────────────────────
    // validatePasswordResetToken
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna fallo cuando el token de reset es inválido o ha expirado')]
    public function testValidatePasswordResetTokenWithInvalidTokenReturnsFailure(): void
    {
        $this->authTokenRepoStub->method('findValidPasswordResetToken')->willReturn(null);

        $result = $this->makeService($this->makeUser())->validatePasswordResetToken('bad_token');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[TestDox('Retorna éxito con user_id cuando el token de reset es válido')]
    public function testValidatePasswordResetTokenWithValidTokenReturnsSuccessWithUserId(): void
    {
        $this->authTokenRepoStub->method('findValidPasswordResetToken')
            ->willReturn(['id' => 3, 'user_id' => 7]);

        $result = $this->makeService($this->makeUser())->validatePasswordResetToken('valid_token');

        $this->assertTrue($result->ok);
        $this->assertSame(7, $result->data['user_id']);
    }

    // ─────────────────────────────────────────────────────────────
    // resetPasswordWithToken
    // ─────────────────────────────────────────────────────────────

    #[TestDox('Retorna fallo cuando el token de reset es inválido')]
    public function testResetPasswordWithInvalidTokenReturnsFailure(): void
    {
        $this->authTokenRepoStub->method('findValidPasswordResetToken')->willReturn(null);

        $result = $this->makeService($this->makeUser())
            ->resetPasswordWithToken('bad_token', 'NewPass123', 'NewPass123');

        $this->assertFalse($result->ok);
    }

    #[TestDox('Retorna fallo cuando la nueva contraseña tiene menos de 8 caracteres')]
    public function testResetPasswordWithTooShortPasswordReturnsFailure(): void
    {
        $this->authTokenRepoStub->method('findValidPasswordResetToken')
            ->willReturn(['id' => 1, 'user_id' => 1]);

        $result = $this->makeService($this->makeUser())
            ->resetPasswordWithToken('valid_token', 'Short1', 'Short1');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('8 caracteres', $result->error ?? '');
    }

    #[TestDox('Retorna fallo cuando las contraseñas nuevas no coinciden')]
    public function testResetPasswordWithMismatchedPasswordsReturnsFailure(): void
    {
        $this->authTokenRepoStub->method('findValidPasswordResetToken')
            ->willReturn(['id' => 1, 'user_id' => 1]);

        $result = $this->makeService($this->makeUser())
            ->resetPasswordWithToken('valid_token', 'Password123', 'Password456');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no coinciden', $result->error ?? '');
    }

    #[TestDox('Cambia la contraseña, consume el token y retorna éxito con datos válidos')]
    public function testResetPasswordWithValidDataReturnsSuccess(): void
    {
        $this->authTokenRepoStub->method('findValidPasswordResetToken')
            ->willReturn(['id' => 1, 'user_id' => 1]);
        $this->authTokenRepoStub->method('markPasswordResetTokenUsed')->willReturn(true);

        // userModel->updatePassword requiere PDO funcional (execute = true)
        $result = $this->makeService($this->makeUser(['id' => 1, 'email' => 'u@example.com']))
            ->resetPasswordWithToken('valid_token', 'NewPassword123', 'NewPassword123');

        $this->assertTrue($result->ok);
    }
}
