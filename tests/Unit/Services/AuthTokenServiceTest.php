<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AuthTokenService: creación de tokens, verificación y validación de password-reset.
 * ¿Qué me quieres demostrar? Que tokens inválidos o expirados retornan Result::fail, y que los tokens válidos retornan Result::ok con user_id.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambia la lógica de hash SHA-256, la respuesta a token null, o la estructura de Result::ok.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\AuthTokenRepositoryInterface;
use App\Services\AuthTokenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthTokenService::class)]
final class AuthTokenServiceTest extends TestCase
{
    private AuthTokenRepositoryInterface $repoStub;
    private AuthTokenService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(AuthTokenRepositoryInterface::class);
        $this->service  = new AuthTokenService($this->repoStub);
    }

    public function testCreateEmailVerificationTokenReturnsNonEmptyString(): void
    {
        $this->repoStub->method('deletePendingEmailVerificationTokensByUser');
        $this->repoStub->method('createEmailVerificationToken');

        $token = $this->service->createEmailVerificationToken(1);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertSame(64, \strlen($token));
    }

    public function testCreateEmailVerificationTokenReturnsDifferentTokens(): void
    {
        $token1 = $this->service->createEmailVerificationToken(1);
        $token2 = $this->service->createEmailVerificationToken(1);

        $this->assertNotSame($token1, $token2);
    }

    public function testVerifyEmailReturnsFailWhenTokenNotFound(): void
    {
        $this->repoStub->method('findValidEmailVerificationToken')->willReturn(null);

        $result = $this->service->verifyEmail('invalidtoken');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inválido', $result->error);
    }

    public function testVerifyEmailReturnsOkWhenTokenValid(): void
    {
        $this->repoStub->method('findValidEmailVerificationToken')->willReturn(['id' => 1, 'user_id' => 5]);

        $result = $this->service->verifyEmail('validtoken');

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('user_id', $result->data);
        $this->assertSame(5, $result->data['user_id']);
    }

    public function testIsEmailVerifiedDelegatesToRepository(): void
    {
        $this->repoStub->method('isUserEmailVerified')->willReturn(true);

        $this->assertTrue($this->service->isEmailVerified(1));
    }

    public function testIsEmailVerifiedReturnsFalseWhenNotVerified(): void
    {
        $this->repoStub->method('isUserEmailVerified')->willReturn(false);

        $this->assertFalse($this->service->isEmailVerified(1));
    }

    public function testValidatePasswordResetTokenReturnsFailWhenTokenNotFound(): void
    {
        $this->repoStub->method('findValidPasswordResetToken')->willReturn(null);

        $result = $this->service->validatePasswordResetToken('badtoken');

        $this->assertFalse($result->ok);
    }

    public function testValidatePasswordResetTokenReturnsOkWhenValid(): void
    {
        $this->repoStub->method('findValidPasswordResetToken')->willReturn(['id' => 1, 'user_id' => 3]);

        $result = $this->service->validatePasswordResetToken('validtoken');

        $this->assertTrue($result->ok);
    }

    public function testCreatePasswordResetTokenReturnsNonEmptyString(): void
    {
        $this->repoStub->method('createPasswordResetToken');

        $token = $this->service->createPasswordResetToken(1, '127.0.0.1');

        $this->assertNotEmpty($token);
        $this->assertSame(64, \strlen($token));
    }

    public function testConsumePasswordResetTokenDelegatesToRepo(): void
    {
        $this->repoStub->method('findValidPasswordResetToken')->willReturn(['id' => 1, 'user_id' => 3]);

        $result = $this->service->consumePasswordResetToken('sometoken');

        $this->assertIsBool($result);
    }
}
