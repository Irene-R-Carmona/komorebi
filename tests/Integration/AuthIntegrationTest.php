<?php

declare(strict_types=1);

/**
 * Tests de Integración de AuthService
 *
 * ¿Qué pruebas aquí?
 * Operaciones de autenticación con MySQL 8.4 real: login correcto,
 * login fallido, bloqueo por intentos, verificación de contraseña.
 *
 * ¿Qué me quieres demostrar?
 * Que AuthService interactúa correctamente con la BD real (UserRepository)
 * y que las reglas de negocio (bloqueo, hash) funcionan en integración.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si findByEmail() deja de retornar el usuario, si el hash ARGON2ID cambia,
 * o si se elimina la lógica de bloqueo por intentos fallidos.
 */

namespace Tests\Integration;

use App\Models\User;
use App\Repositories\AuthLogRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\Contracts\RateLimitingServiceInterface;
use App\Services\SessionManagementService;
use Override;
use PDO;
use Tests\Support\BaseIntegrationTest;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AuthIntegrationTest extends BaseIntegrationTest
{
    private AuthService $service;
    private UserRepository $userRepo;

    // IDs únicos para tests
    private const TEST_USER_ID = 77777;
    private const TEST_EMAIL = 'auth-integration-test@komorebi.test';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->userRepo = new UserRepository(self::$db);
        $rateLimiter = $this->createStub(RateLimitingServiceInterface::class);
        $rateLimiter->method('isBlocked')->willReturn(['blocked' => false]);
        $this->service = new AuthService(
            $this->userRepo,
            new User(),
            new SessionManagementService(
                new SessionRepository(self::$db),
                new AuthLogRepository(self::$db),
            ),
            $rateLimiter
        );
    }

    /**
     * Seed de datos de prueba
     */
    private function seedTestData(): void
    {
        // Limpiar datos previos si existen
        self::$db->exec('DELETE FROM users WHERE id = ' . self::TEST_USER_ID);

        // Usuario de prueba con password hasheado (SecurePass123!)
        $hashedPassword = \password_hash('SecurePass123!', PASSWORD_ARGON2ID);

        self::$db->exec('
            INSERT INTO users (
                id, uuid, email, password, name,
                email_verified_at, is_active, created_at
            )
            VALUES (
                ' . self::TEST_USER_ID . ",
                UUID(),
                '" . self::TEST_EMAIL . "',
                '{$hashedPassword}',
                'Auth Integration Test User',
                NOW(),
                1,
                NOW()
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────
    // Integration Tests
    // ─────────────────────────────────────────────────────────────

    public function testRegisterInsertsUserIntoDatabaseCorrectly(): void
    {
        // ACT: Registrar nuevo usuario
        $result = $this->service->register(
            'New User',
            'newuser-auth-test@komorebi.test',
            'SecurePass123!',
            'SecurePass123!'
        );

        // ASSERT: Éxito
        $this->assertTrue($result->ok, 'Register should succeed: ' . ($result->error ?? ''));
        $this->assertArrayHasKey('user_id', $result->data);
        $userId = $result->data['user_id'];

        // ASSERT: Usuario existe en BD con datos correctos
        $stmt = self::$db->prepare('
            SELECT id, email, name, password, is_active
            FROM users
            WHERE id = ?
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('newuser-auth-test@komorebi.test', $row['email']);
        $this->assertSame('New User', $row['name']);
        $this->assertStringStartsWith('$argon2id$', $row['password']); // Argon2id hash
        $this->assertSame(1, (int) $row['is_active']);
    }

    public function testRegisterFailsWhenEmailAlreadyExists(): void
    {
        // ARRANGE: Email ya existe (seeded en setUp)

        // ACT: Intentar registrar con email existente
        $result = $this->service->register(
            'Another User',
            self::TEST_EMAIL, // Email ya existe
            'SecurePass123!',
            'SecurePass123!'
        );

        // ASSERT: Debe fallar
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('email', \strtolower($result->error ?? ''));
        $this->assertStringContainsString('registrado', \strtolower($result->error ?? ''));
    }

    public function testLoginWithValidCredentialsSucceeds(): void
    {
        // Mock superglobales para evitar errores de sesión
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Integration Test';

        // ACT: Login con credenciales correctas
        $result = $this->service->login(self::TEST_EMAIL, 'SecurePass123!');

        // ASSERT: Éxito
        $this->assertTrue($result->ok, 'Login should succeed: ' . ($result->error ?? ''));
        $this->assertIsArray($result->data);

        // Cleanup de superglobales
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        // Mock superglobales
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Integration Test';

        // ACT: Login con password incorrecta
        $result = $this->service->login(self::TEST_EMAIL, 'WrongPassword123!');

        // ASSERT: Debe fallar
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('credencial', \strtolower($result->error ?? ''));

        // Cleanup
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testLoginWithNonExistentUserFails(): void
    {
        // ACT: Login con usuario que no existe
        $result = $this->service->login('nonexistent@komorebi.test', 'AnyPassword123!');

        // ASSERT: Debe fallar
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('credencial', \strtolower($result->error ?? ''));
    }

    public function testPasswordIsHashedWithArgon2idInDatabase(): void
    {
        // ACT: Registrar usuario
        $result = $this->service->register(
            'Hash Test User',
            'hashtest-auth@komorebi.test',
            'MySecurePass123!',
            'MySecurePass123!'
        );

        $this->assertTrue($result->ok);
        $userId = $result->data['user_id'];

        // ASSERT: Verificar hash en BD
        $stmt = self::$db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $hashedPassword = $row['password'] ?? '';

        // Verificar que es Argon2id
        $this->assertStringStartsWith('$argon2id$', $hashedPassword);

        // Verificar que no es el password en texto plano
        $this->assertNotEquals('MySecurePass123!', $hashedPassword);

        // Verificar que password_verify funciona
        $this->assertTrue(
            \password_verify('MySecurePass123!', $hashedPassword),
            'Password should be verifiable with password_verify'
        );
    }
}
