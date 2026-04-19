<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de seguridad del UserRepository: valida que getSelectFields() jamás expone
 * campos sensibles (password, IPs, locks) en consultas estándar.
 *
 * ¿Qué me quieres demostrar?
 * Que findByEmail() y findById() devuelven arrays SIN password, last_ip_address,
 * locked_until ni login_attempts — y que findByEmailWithCredentials() SÍ los incluye
 * (para confirmar que el método explícito seguro funciona como contrato).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si alguien añade 'password' a getSelectFields(), o elimina findByEmailWithCredentials(),
 * o cambia los campos devueltos por findByEmail()/findById().
 */

namespace Tests\Integration\Repositories;

use App\Repositories\UserRepository;
use Override;
use ReflectionClass;
use Tests\Support\BaseIntegrationTest;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class UserRepositorySecurityTest extends BaseIntegrationTest
{
    private UserRepository $repo;

    private const TEST_ID = 78001;
    private const TEST_EMAIL = 'security-test-user@komorebi.test';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository(self::$db);
        $this->seedTestUser();
    }

    private function seedTestUser(): void
    {
        self::$db->exec('DELETE FROM users WHERE id = ' . self::TEST_ID);
        $hash = \password_hash('TestPass123!', \PASSWORD_ARGON2ID);
        $stmt = self::$db->prepare(
            'INSERT INTO users (id, uuid, email, password, name, email_verified_at, is_active, created_at)
             VALUES (:id, UUID(), :email, :password, :name, NOW(), 1, NOW())'
        );
        $stmt->execute([
            ':id' => self::TEST_ID,
            ':email' => self::TEST_EMAIL,
            ':password' => $hash,
            ':name' => 'Security Test User',
        ]);
    }

    public function testGetSelectFieldsNeverContainsPassword(): void
    {
        $reflection = new ReflectionClass($this->repo);
        $method = $reflection->getMethod('getSelectFields');
        $fields = $method->invoke($this->repo);

        $this->assertIsArray($fields);
        $this->assertNotContains('password', $fields);
        $this->assertNotContains('last_ip_address', $fields);
        $this->assertNotContains('locked_until', $fields);
        $this->assertNotContains('login_attempts', $fields);
    }

    public function testFindByEmailDoesNotReturnPassword(): void
    {
        $user = $this->repo->findByEmail(self::TEST_EMAIL);

        $this->assertIsArray($user, 'findByEmail debe devolver el usuario de prueba');
        $this->assertArrayNotHasKey('password', $user, 'findByEmail no debe devolver password');
        $this->assertArrayNotHasKey('last_ip_address', $user);
        $this->assertArrayNotHasKey('locked_until', $user);
        $this->assertArrayNotHasKey('login_attempts', $user);
    }

    public function testFindByIdDoesNotReturnPassword(): void
    {
        $user = $this->repo->findById(self::TEST_ID);

        $this->assertIsArray($user, 'findById debe devolver el usuario de prueba');
        $this->assertArrayNotHasKey('password', $user, 'findById no debe devolver password');
        $this->assertArrayNotHasKey('last_ip_address', $user);
        $this->assertArrayNotHasKey('locked_until', $user);
    }

    public function testFindByEmailWithCredentialsDoesReturnPassword(): void
    {
        $user = $this->repo->findByEmailWithCredentials(self::TEST_EMAIL);

        $this->assertIsArray($user, 'findByEmailWithCredentials debe devolver el usuario de prueba');
        $this->assertArrayHasKey('password', $user, 'findByEmailWithCredentials debe devolver password para autenticación');
        $this->assertArrayHasKey('login_attempts', $user);
        $this->assertArrayHasKey('locked_until', $user);
        $this->assertArrayHasKey('last_ip_address', $user);
    }
}
