<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthService;
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
    private UserRepository $userRepoMock;
    private User $userModelMock;

    protected function setUp(): void
    {
        // Mock del repositorio UserRepository
        $this->userRepoMock = $this->createStub(UserRepository::class);
        $this->service = new AuthService($this->userRepoMock);

        // Mock del modelo User utilizado por algunos tests
        $this->userModelMock = $this->createStub(User::class);

        // Simular superglobales (usar IP única por test para evitar rate-limits acumulados)
        $_SERVER['REMOTE_ADDR'] = '127.0.0.' . (string) random_int(2, 254);
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
        $this->assertStringContainsString('email', strtolower($result->error ?? ''));
    }

    /**
     * @throws RandomException
     */
    public function testLoginWithEmptyPasswordReturnsError(): void
    {
        $result = $this->service->login('test@example.com', '');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('contraseña', strtolower($result->error ?? ''));
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
        $this->assertStringContainsString('credencial', strtolower($result->error ?? ''));
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
        $this->assertStringContainsString('credencial', strtolower($result->error ?? ''));
    }

    // ─────────────────────────────────────────────────────────────
    // Password Security
    // ─────────────────────────────────────────────────────────────

    public function testPasswordIsHashedWithArgon2id(): void
    {
        $plainPassword = 'test_password_123';
        $hashedPassword = password_hash($plainPassword, PASSWORD_ARGON2ID);

        // Verificar algoritmo Argon2id
        $this->assertStringStartsWith('$argon2id$', $hashedPassword);

        // Verificar no es reversible
        $this->assertNotEquals($plainPassword, $hashedPassword);

        // Verificar password_verify funciona
        $this->assertTrue(password_verify($plainPassword, $hashedPassword));
    }

    public function testPasswordHashIsNotReversible(): void
    {
        $password = 'my_secret_password';
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        // Hash no debe contener password en texto plano
        $this->assertStringNotContainsString($password, $hash);
        $this->assertGreaterThan(60, strlen($hash)); // Hash Argon2id es largo
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
        $mock = $this->createMock(UserRepository::class);
        $mock->expects($this->once())
            ->method('findByEmail')
            ->with($this->equalTo('test@example.com'))
            ->willReturn(null);

        // Usar un servicio construido con el mock que verifica el argumento
        $service = new AuthService($mock);
        $service->login('TEST@EXAMPLE.COM', 'password123');

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // Register - Validaciones y éxito
    // ─────────────────────────────────────────────────────────────

    // Test comentado: requiere integration test con sesiones reales
    // public function testRegisterWithValidDataSuccess(): void

    public function testRegisterValidatesNameLength(): void
    {
        $result = $this->service->register(str_repeat('A', 101), 'user@example.com', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('nombre', strtolower($result->error ?? ''));
    }

    public function testRegisterWithMismatchedPasswordsFails(): void
    {
        $result = $this->service->register('User', 'user@example.com', 'SecurePass123!', 'DifferentPass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('contraseñ', strtolower($result->error ?? ''));
    }

    public function testRegisterWithExistingEmailFails(): void
    {
        // Mock: email ya existe
        $this->userRepoMock
            ->method('emailExists')
            ->willReturn(true);

        $result = $this->service->register('User', 'existing@example.com', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('email', strtolower($result->error ?? ''));
    }

    public function testRegisterWithEmptyNameFails(): void
    {
        $result = $this->service->register('', 'user@example.com', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('nombre', strtolower($result->error ?? ''));
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

    // Test comentado: requiere integration test con audit logs
    // public function testLoginWithWrongPasswordFails(): void

    public function testRegisterValidatesPasswordMinLength(): void
    {
        $result = $this->service->register('User', 'user@example.com', 'Short1!', 'Short1!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('contraseña', strtolower($result->error ?? ''));
    }

    public function testRegisterWithInvalidEmailFormatFails(): void
    {
        $result = $this->service->register('User', 'not-an-email', 'SecurePass123!', 'SecurePass123!');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('email', strtolower($result->error ?? ''));
    }
}
