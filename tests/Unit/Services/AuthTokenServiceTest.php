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

use App\Repositories\Contracts\AuthTokenRepositoryInterface;
use App\Services\AuthTokenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(\App\Services\AuthTokenService::class)]
final class AuthTokenServiceTest extends TestCase
{
    private function makeService(?AuthTokenRepositoryInterface $repo = null): AuthTokenService
    {
        $repo ??= $this->createStub(AuthTokenRepositoryInterface::class);

        return new AuthTokenService($repo);
    }

    // ─────────────────────────────────────────────────────────────
    // createEmailVerificationToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_createEmailVerificationToken_returns_non_empty_hex_string(): void
    {
        $token = $this->makeService()->createEmailVerificationToken(1);

        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    // ─────────────────────────────────────────────────────────────
    // verifyEmail
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_verifyEmail_with_valid_token_returns_ok_result_with_user_id(): void
    {
        $repo = $this->createStub(AuthTokenRepositoryInterface::class);
        $repo->method('findValidEmailVerificationToken')->willReturn(['id' => 7, 'user_id' => 42]);

        $result = $this->makeService($repo)->verifyEmail('anytoken');

        $this->assertTrue($result->ok);
        $this->assertSame(42, $result->data['user_id']);
    }

    #[Test]
    public function test_verifyEmail_with_invalid_or_expired_token_returns_failure(): void
    {
        $repo = $this->createStub(AuthTokenRepositoryInterface::class);
        $repo->method('findValidEmailVerificationToken')->willReturn(null);

        $result = $this->makeService($repo)->verifyEmail('expiredorinvalidtoken');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[Test]
    public function test_verifyEmail_executes_updates_to_mark_token_as_consumed(): void
    {
        $repo = $this->createMock(AuthTokenRepositoryInterface::class);
        $repo->method('findValidEmailVerificationToken')->willReturn(['id' => 1, 'user_id' => 5]);
        $repo->expects($this->once())->method('markEmailVerificationTokenVerified')->with(1);
        $repo->expects($this->once())->method('markUserEmailVerified')->with(5);

        $this->makeService($repo)->verifyEmail('validtoken');
    }

    // ─────────────────────────────────────────────────────────────
    // isEmailVerified
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_isEmailVerified_returns_true_when_email_is_verified(): void
    {
        $repo = $this->createStub(AuthTokenRepositoryInterface::class);
        $repo->method('isUserEmailVerified')->willReturn(true);

        $this->assertTrue($this->makeService($repo)->isEmailVerified(10));
    }

    #[Test]
    public function test_isEmailVerified_returns_false_when_email_is_not_verified(): void
    {
        $repo = $this->createStub(AuthTokenRepositoryInterface::class);
        $repo->method('isUserEmailVerified')->willReturn(false);

        $this->assertFalse($this->makeService($repo)->isEmailVerified(10));
    }

    // ─────────────────────────────────────────────────────────────
    // createPasswordResetToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_createPasswordResetToken_returns_non_empty_hex_string(): void
    {
        $token = $this->makeService()->createPasswordResetToken(1, '127.0.0.1', 'TestAgent/1.0');

        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    // ─────────────────────────────────────────────────────────────
    // validatePasswordResetToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_validatePasswordResetToken_with_valid_token_returns_ok_with_user_id(): void
    {
        $repo = $this->createStub(AuthTokenRepositoryInterface::class);
        $repo->method('findValidPasswordResetToken')->willReturn(['user_id' => 99]);

        $result = $this->makeService($repo)->validatePasswordResetToken('validresettoken');

        $this->assertTrue($result->ok);
        $this->assertSame(99, $result->data['user_id']);
    }

    #[Test]
    public function test_validatePasswordResetToken_with_invalid_token_returns_failure(): void
    {
        $repo = $this->createStub(AuthTokenRepositoryInterface::class);
        $repo->method('findValidPasswordResetToken')->willReturn(null);

        $result = $this->makeService($repo)->validatePasswordResetToken('invalidtoken');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // consumePasswordResetToken
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_consumePasswordResetToken_returns_true_when_token_exists(): void
    {
        $repo = $this->createStub(AuthTokenRepositoryInterface::class);
        $repo->method('markPasswordResetTokenUsed')->willReturn(true);

        $this->assertTrue($this->makeService($repo)->consumePasswordResetToken('someunusedtoken'));
    }
}
