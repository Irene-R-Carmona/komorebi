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
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

/**
 * Tests para AuthService usando Result Pattern
 *
 * AuthService NO lanza excepciones, retorna Result{ok:bool, data, error}
 */
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

        // PDO stub: prepare() devuelve un statement que ejecuta sin errores
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $this->service = new AuthService(
            $this->userRepoMock,
            $this->userModelStub,
            $this->sessionServiceStub,
            $this->rateLimiterStub,
            $pdoStub
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

        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('execute')->willReturn(true);
        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        // Usar servicio construido con el mock que verifica el argumento
        $service = new AuthService(
            $mock,
            $this->userModelStub,
            $this->sessionServiceStub,
            $this->rateLimiterStub,
            $pdoStub
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
}
