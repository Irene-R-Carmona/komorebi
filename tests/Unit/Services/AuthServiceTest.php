<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Models\Contracts\UserModelInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuthService;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\Contracts\SessionManagementServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

/**
 * Tests para AuthService usando Result Pattern
 *
 * AuthService NO lanza excepciones, retorna Result{ok:bool, data, error}
 */
#[CoversClass(AuthService::class)]
final class AuthServiceTest extends TestCase
{
    private AuthService $service;
    /** @var \PHPUnit\Framework\MockObject\Stub&UserRepositoryInterface */
    private UserRepositoryInterface $userRepoMock;
    /** @var \PHPUnit\Framework\MockObject\Stub&RateLimitingServiceInterface */
    private RateLimitingServiceInterface $rateLimiterStub;
    /** @var \PHPUnit\Framework\MockObject\Stub&SessionManagementServiceInterface */
    private SessionManagementServiceInterface $sessionServiceStub;
    /** @var \PHPUnit\Framework\MockObject\Stub&UserModelInterface */
    private UserModelInterface $userModelStub;

    protected function setUp(): void
    {
        $this->userRepoMock = $this->createStub(UserRepositoryInterface::class);
        $this->rateLimiterStub = $this->createStub(RateLimitingServiceInterface::class);
        $this->sessionServiceStub = $this->createStub(SessionManagementServiceInterface::class);
        $this->userModelStub = $this->createStub(UserModelInterface::class);

        $this->service = new AuthService(
            $this->userRepoMock,
            $this->userModelStub,
            $this->sessionServiceStub,
            $this->rateLimiterStub
        );

        // Simular superglobales (IP única por test para evitar rate-limits acumulados)
        $_SERVER['REMOTE_ADDR'] = '127.0.0.' . (string) \random_int(2, 254);
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    // ─────────────────────────────────────────────────────────────
    // Login - Validaciones básicas
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws RandomException
     */
    public function testLoginWithEmptyEmailReturnsError(): void
    {
        $result = $this->service->login('', 'password123');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('email', \strtolower($result->error ?? ''));
    }

    /**
     * @throws RandomException
     */
    public function testLoginWithEmptyPasswordReturnsError(): void
    {
        $result = $this->service->login('test@example.com', '');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('contraseña', \strtolower($result->error ?? ''));
    }

    /**
     * @throws RandomException
     */
    public function testLoginWithInvalidEmailFormatReturnsError(): void
    {
        // Mock: usuario no existe (email inválido no se valida hasta BD)
        $this->userRepoMock
            ->method('findByEmail')
            ->willReturn(null);

        $result = $this->service->login('not-an-email', 'password123');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('credencial', \strtolower($result->error ?? ''));
    }

    // ─────────────────────────────────────────────────────────────
    // Login - Usuario no existe
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws RandomException
     */
    public function testLoginWithNonExistentUserReturnsError(): void
    {
        $this->userRepoMock
            ->method('findByEmail')
            ->willReturn(null);

        $result = $this->service->login('noexiste@example.com', 'password123');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('credencial', \strtolower($result->error ?? ''));
    }

    // ─────────────────────────────────────────────────────────────
    // Password Security
    // ─────────────────────────────────────────────────────────────

    public function testPasswordIsHashedWithArgon2id(): void
    {
        $plainPassword = 'test_password_123';
        $hashedPassword = \password_hash($plainPassword, PASSWORD_ARGON2ID);

        // Verificar algoritmo Argon2id
        $this->assertStringStartsWith('$argon2id$', $hashedPassword);

        // Verificar no es reversible
        $this->assertNotEquals($plainPassword, $hashedPassword);

        // Verificar password_verify funciona
        $this->assertTrue(\password_verify($plainPassword, $hashedPassword));
    }

    public function testPasswordHashIsNotReversible(): void
    {
        $password = 'my_secret_password';
        $hash = \password_hash($password, PASSWORD_ARGON2ID);

        // Hash no debe contener password en texto plano
        $this->assertStringNotContainsString($password, $hash);
        $this->assertGreaterThan(60, \strlen($hash)); // Hash Argon2id es largo
    }

    // ─────────────────────────────────────────────────────────────
    // Email normalization
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws RandomException
     */
    public function testEmailIsNormalizedToLowercase(): void
    {
        // Construir un mock local con expectativa sobre el argumento
        $mock = $this->createMock(UserRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('findByEmailWithCredentials')
            ->with($this->equalTo('test@example.com'))
            ->willReturn(null);

        // Usar servicio construido con el mock que verifica el argumento
        $service = new AuthService(
            $mock,
            $this->userModelStub,
            $this->sessionServiceStub,
            $this->rateLimiterStub
        );
        $service->login('TEST@EXAMPLE.COM', 'password123');

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // Register - Validaciones y éxito
    // ─────────────────────────────────────────────────────────────

    public function testRegisterWithValidDataSuccess(): void
    {
        $this->userRepoMock->method('emailExists')->willReturn(false);
        $this->userRepoMock->method('create')->willReturn(1);
        $this->userRepoMock->method('findById')->willReturn([
            'id' => 1,
            'email' => 'user@example.com',
            'name' => 'Test User',
        ]);

        $result = $this->service->register('Test User', 'user@example.com', 'SecurePass123!', 'SecurePass123!');

        $this->assertTrue($result->ok);
    }

    public function testRegisterValidatesNameLength(): void
    {
        $result = $this->service->register(\str_repeat('A', 101), 'user@example.com', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('nombre', \strtolower($result->error ?? ''));
    }

    public function testRegisterWithMismatchedPasswordsFails(): void
    {
        $result = $this->service->register('User', 'user@example.com', 'SecurePass123!', 'DifferentPass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('contraseñ', \strtolower($result->error ?? ''));
    }

    public function testRegisterWithExistingEmailFails(): void
    {
        // Mock: email ya existe
        $this->userRepoMock
            ->method('emailExists')
            ->willReturn(true);

        $result = $this->service->register('User', 'existing@example.com', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('email', \strtolower($result->error ?? ''));
    }

    public function testRegisterWithEmptyNameFails(): void
    {
        $result = $this->service->register('', 'user@example.com', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('nombre', \strtolower($result->error ?? ''));
    }

    public function testRegisterHashesPasswordCorrectly(): void
    {
        $plainPassword = 'SecurePass123!';
        $capturedHash = null;

        // Mock: capturar el hash generado
        $this->userRepoMock
            ->method('emailExists')
            ->willReturn(false);

        $this->userRepoMock
            ->method('create')
            ->willReturnCallback(function ($data) use (&$capturedHash, $plainPassword) {
                $capturedHash = $data['password'] ?? null;
                $this->assertNotNull($capturedHash, 'Password hash should not be null');
                $this->assertNotEquals($plainPassword, $capturedHash, 'Password should be hashed');
                $this->assertStringStartsWith('$argon2id$', $capturedHash, 'Should use Argon2id');

                return 1; // Retorna int (user ID)
            });

        // Mock findById para auto-login
        $this->userRepoMock
            ->method('findById')
            ->willReturn(['id' => 1, 'email' => 'test@example.com']);

        $this->service->register('User', 'test@example.com', $plainPassword, $plainPassword);

        $this->assertNotNull($capturedHash);
    }

    // ─────────────────────────────────────────────────────────────
    // Login - Tests que requieren integration (sesiones/auditoría)
    // ─────────────────────────────────────────────────────────────

    public function testLoginWithWrongPasswordFails(): void
    {
        $hash = \password_hash('correct_password123!', PASSWORD_ARGON2ID);
        $this->userRepoMock->method('findByEmail')->willReturn([
            'id' => 1,
            'email' => 'user@example.com',
            'password' => $hash,
            'is_active' => 1,
            'locked_until' => null,
            'name' => 'Test User',
            'login_attempts' => 0,
        ]);
        $this->userModelStub->method('isLocked')->willReturn(false);
        $this->userModelStub->method('verifyPassword')->willReturn(false);

        $result = $this->service->login('user@example.com', 'wrong_password');

        $this->assertFalse($result->ok);
    }

    public function testRegisterValidatesPasswordMinLength(): void
    {
        $result = $this->service->register('User', 'user@example.com', 'Short1!', 'Short1!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('contraseña', \strtolower($result->error ?? ''));
    }

    public function testRegisterWithInvalidEmailFormatFails(): void
    {
        $result = $this->service->register('User', 'not-an-email', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('email', \strtolower($result->error ?? ''));
    }

    // ─────────────────────────────────────────────────────────────
    // Login - Cuenta bloqueada / desactivada
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws RandomException
     */
    #[TestDox('El login retorna error cuando la cuenta está bloqueada temporalmente')]
    public function testLoginWithLockedUserReturnsError(): void
    {
        // arrange
        $hash = \password_hash('password123', PASSWORD_ARGON2ID);
        $this->userRepoMock->method('findByEmailWithCredentials')->willReturn([
            'id'             => 1,
            'email'          => 'locked@example.com',
            'password'       => $hash,
            'is_active'      => 1,
            'locked_until'   => \date('Y-m-d H:i:s', \time() + 3600),
            'login_attempts' => 5,
            'name'           => 'Locked User',
        ]);
        $this->userModelStub->method('isLocked')->willReturn(true);
        $this->userModelStub->method('lockoutMinutesRemaining')->willReturn(30);

        // act
        $result = $this->service->login('locked@example.com', 'password123');

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('bloqueada', \strtolower($result->error ?? ''));
    }

    /**
     * @throws RandomException
     */
    #[TestDox('El login retorna error cuando la cuenta del usuario está desactivada')]
    public function testLoginWithInactiveUserReturnsError(): void
    {
        // arrange
        $hash = \password_hash('password123', PASSWORD_ARGON2ID);
        $this->userRepoMock->method('findByEmailWithCredentials')->willReturn([
            'id'             => 2,
            'email'          => 'inactive@example.com',
            'password'       => $hash,
            'is_active'      => 0,
            'locked_until'   => null,
            'login_attempts' => 0,
            'name'           => 'Inactive User',
        ]);
        $this->userModelStub->method('isLocked')->willReturn(false);

        // act
        $result = $this->service->login('inactive@example.com', 'password123');

        // assert
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('desactivada', \strtolower($result->error ?? ''));
    }

    // ─────────────────────────────────────────────────────────────
    // Logout
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws RandomException
     */
    #[TestDox('El logout se ejecuta sin lanzar excepciones aunque no haya sesión activa')]
    public function testLogoutRunsWithoutErrors(): void
    {
        // arrange: sessionService stub acepta cualquier llamada a logAuthEvent
        // act
        $this->service->logout();

        // assert: si llegamos aquí sin excepción el logout fue correcto
        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // Login - Éxito
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws RandomException
     */
    #[TestDox('El login con credenciales válidas retorna Result::ok')]
    public function testLoginWithValidCredentialsReturnsOkResult(): void
    {
        // arrange
        $hash = \password_hash('correct_password123!', PASSWORD_ARGON2ID);
        $this->userRepoMock->method('findByEmailWithCredentials')->willReturn([
            'id'             => 1,
            'email'          => 'user@example.com',
            'password'       => $hash,
            'is_active'      => 1,
            'locked_until'   => null,
            'login_attempts' => 0,
            'name'           => 'Test User',
            'cafe_id'        => null,
        ]);
        $this->userModelStub->method('isLocked')->willReturn(false);
        $this->userModelStub->method('verifyPassword')->willReturn(true);
        $this->userModelStub->method('getRoles')->willReturn([]);

        // act
        $result = $this->service->login('user@example.com', 'correct_password123!');

        // assert
        $this->assertTrue($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // Rate limiting
    // ─────────────────────────────────────────────────────────────

    /**
     * @throws RandomException
     */
    #[TestDox('El rate limiting se omite en contexto CLI — el flujo prosigue hasta validar credenciales')]
    public function testLoginSkipsRateLimitingWhenRunningUnderCli(): void
    {
        // arrange: rate limiter configurado para bloquear cualquier intento
        $this->rateLimiterStub->method('isBlocked')->willReturn([
            'blocked'           => true,
            'minutes_remaining' => 15,
        ]);
        // sin usuario en BD — si el rate limit se aplicase el error sería "demasiados intentos"
        $this->userRepoMock->method('findByEmailWithCredentials')->willReturn(null);

        // act
        $result = $this->service->login('user@example.com', 'password123');

        // assert: el error es de credenciales (no de rate limit), confirmando que el bypass CLI actuó
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('credencial', \strtolower($result->error ?? ''));
    }
}
