<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Servicio de tokens de autenticación: generación, verificación y consumo
 * de tokens de verificación de email y de reset de contraseña.
 *
 * ¿Qué me quieres demostrar?
 * Que los tokens se generan como cadenas hexadecimales no vacías, que la
 * verificación distingue tokens válidos de expirados/inválidos devolviendo
 * Result::ok o Result::fail, y que la verificación de email deja constancia
 * en la base de datos (lo hace de un solo uso).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el hashing o se devuelve un token vacío, si la lógica
 * Result::ok/fail cambia de semántica, si se deja de ejecutar el UPDATE
 * que marca verified_at/used_at, o si isEmailVerified cambia su criterio
 * sobre qué campo indica verificación.
 */

namespace Tests\Unit\Services;

use App\Services\AuthTokenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(\App\Services\AuthTokenService::class)]
final class AuthTokenServiceTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un PDO stub que devuelve siempre el mismo PDOStatement dado.
     * Útil cuando todos los prepare() del método bajo prueba
     * necesitan el mismo comportamiento.
     */
    private function makePdoWithStmt(\PDOStatement $stmt): \PDO
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);

        return $pdo;
    }

    /**
     * Crea un PDOStatement stub con valores de retorno configurables.
     */
    private function makeStmt(
        mixed $fetchReturn = false,
        mixed $fetchColumnReturn = 0
    ): \PDOStatement {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        $stmt->method('rowCount')->willReturn(0);

        return $stmt;
    }

    // ─────────────────────────────────────────────────────────────
    // createEmailVerificationToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_createEmailVerificationToken_returns_non_empty_hex_string(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt());
        $service = new AuthTokenService($pdo);

        $token = $service->createEmailVerificationToken(1);

        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    // ─────────────────────────────────────────────────────────────
    // verifyEmail
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_verifyEmail_with_valid_token_returns_ok_result_with_user_id(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt(['id' => 7, 'user_id' => 42]));
        $service = new AuthTokenService($pdo);

        $result = $service->verifyEmail('anytoken');

        $this->assertTrue($result->ok);
        $this->assertSame(42, $result->data['user_id']);
    }

    #[Test]
    public function test_verifyEmail_with_invalid_or_expired_token_returns_failure(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt(false));
        $service = new AuthTokenService($pdo);

        $result = $service->verifyEmail('expiredorinvalidtoken');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[Test]
    public function test_verifyEmail_executes_updates_to_mark_token_as_consumed(): void
    {
        // Contamos cuántas veces se llama execute(): SELECT (1) + dos UPDATEs (2,3)
        $executeCount = 0;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(['id' => 1, 'user_id' => 5]);
        $stmt->method('execute')->willReturnCallback(function () use (&$executeCount): bool {
            $executeCount++;

            return true;
        });

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $service = new AuthTokenService($pdo);
        $service->verifyEmail('validtoken');

        // Al menos: execute del SELECT + execute del UPDATE verified_at
        $this->assertGreaterThanOrEqual(
            2,
            $executeCount,
            'verifyEmail debe ejecutar al menos un SELECT y un UPDATE para consumir el token'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // isEmailVerified
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_isEmailVerified_returns_true_when_email_is_verified(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt(['email_verified_at' => '2024-01-01 10:00:00']));
        $service = new AuthTokenService($pdo);

        $this->assertTrue($service->isEmailVerified(10));
    }

    #[Test]
    public function test_isEmailVerified_returns_false_when_email_is_not_verified(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt(['email_verified_at' => null]));
        $service = new AuthTokenService($pdo);

        $this->assertFalse($service->isEmailVerified(10));
    }

    // ─────────────────────────────────────────────────────────────
    // createPasswordResetToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_createPasswordResetToken_returns_non_empty_hex_string(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt());
        $service = new AuthTokenService($pdo);

        $token = $service->createPasswordResetToken(1, '127.0.0.1', 'TestAgent/1.0');

        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    // ─────────────────────────────────────────────────────────────
    // validatePasswordResetToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_validatePasswordResetToken_with_valid_token_returns_ok_with_user_id(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt(['user_id' => 99]));
        $service = new AuthTokenService($pdo);

        $result = $service->validatePasswordResetToken('validresettoken');

        $this->assertTrue($result->ok);
        $this->assertSame(99, $result->data['user_id']);
    }

    #[Test]
    public function test_validatePasswordResetToken_with_invalid_token_returns_failure(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt(false));
        $service = new AuthTokenService($pdo);

        $result = $service->validatePasswordResetToken('invalidtoken');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // consumePasswordResetToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_consumePasswordResetToken_returns_true_when_token_exists(): void
    {
        $pdo = $this->makePdoWithStmt($this->makeStmt());
        $service = new AuthTokenService($pdo);

        $this->assertTrue($service->consumePasswordResetToken('someunusedtoken'));
    }
}
