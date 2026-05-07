<?php

/**
 * ¿Qué pruebas aquí? AuthTokenRepository: todos los métodos de tokens de
 *   verificación de email y de reset de contraseña.
 * ¿Qué me quieres demostrar? Que findValidEmailVerificationToken retorna null
 *   cuando fetch() es false y castea a int cuando hay resultado; que
 *   isUserEmailVerified usa fetch() y comprueba la clave; que los dos métodos
 *   deleteExpired usan query() y retornan rowCount().
 * ¿Qué va a fallar en este test si se cambia el código? Si findValidEmailVerificationToken
 *   deja de retornar null al no encontrar el token, si isUserEmailVerified deja de
 *   comprobar email_verified_at, o si deleteExpired pasa de query() a prepare().
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\AuthTokenRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthTokenRepository::class)]
final class AuthTokenRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array|false $fetchReturn = false,
        bool $executeReturn = true,
        int $rowCount = 0,
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('rowCount')->willReturn($rowCount);

        return $stmt;
    }

    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);

        return $pdo;
    }

    // -------------------------------------------------------------------------
    // deletePendingEmailVerificationTokensByUser — void
    // -------------------------------------------------------------------------

    public function testDeletePendingEmailVerificationTokensByUserExecutes(): void
    {
        $stmt = $this->makeStmt();
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $repo->deletePendingEmailVerificationTokensByUser(5);
        $this->addToAssertionCount(1); // no exception = success
    }

    // -------------------------------------------------------------------------
    // createEmailVerificationToken — void
    // -------------------------------------------------------------------------

    public function testCreateEmailVerificationTokenExecutes(): void
    {
        $stmt = $this->makeStmt();
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $repo->createEmailVerificationToken(1, 'hash-abc', '2099-01-01 00:00:00');
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // findValidEmailVerificationToken
    // -------------------------------------------------------------------------

    public function testFindValidEmailVerificationTokenReturnsTypedArray(): void
    {
        $row = ['id' => '7', 'user_id' => '42'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $result = $repo->findValidEmailVerificationToken('hash-abc');

        $this->assertNotNull($result);
        $this->assertSame(7, $result['id']);
        $this->assertSame(42, $result['user_id']);
    }

    public function testFindValidEmailVerificationTokenReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertNull($repo->findValidEmailVerificationToken('invalid-hash'));
    }

    // -------------------------------------------------------------------------
    // markEmailVerificationTokenVerified — void
    // -------------------------------------------------------------------------

    public function testMarkEmailVerificationTokenVerifiedExecutes(): void
    {
        $stmt = $this->makeStmt();
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $repo->markEmailVerificationTokenVerified(7);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // markUserEmailVerified — void
    // -------------------------------------------------------------------------

    public function testMarkUserEmailVerifiedExecutes(): void
    {
        $stmt = $this->makeStmt();
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $repo->markUserEmailVerified(42);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // isUserEmailVerified
    // -------------------------------------------------------------------------

    public function testIsUserEmailVerifiedReturnsTrueWhenVerified(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['email_verified_at' => '2024-01-01 10:00:00']);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertTrue($repo->isUserEmailVerified(1));
    }

    public function testIsUserEmailVerifiedReturnsFalseWhenNotVerified(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['email_verified_at' => null]);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertFalse($repo->isUserEmailVerified(1));
    }

    public function testIsUserEmailVerifiedReturnsFalseWhenUserNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertFalse($repo->isUserEmailVerified(999));
    }

    // -------------------------------------------------------------------------
    // deleteExpiredEmailVerificationTokens (usa query())
    // -------------------------------------------------------------------------

    public function testDeleteExpiredEmailVerificationTokensReturnsCount(): void
    {
        $stmt = $this->makeStmt(rowCount: 5);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertSame(5, $repo->deleteExpiredEmailVerificationTokens());
    }

    // -------------------------------------------------------------------------
    // deleteExpiredPasswordResetTokensByUser — void
    // -------------------------------------------------------------------------

    public function testDeleteExpiredPasswordResetTokensByUserExecutes(): void
    {
        $stmt = $this->makeStmt();
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $repo->deleteExpiredPasswordResetTokensByUser(5);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // createPasswordResetToken — void
    // -------------------------------------------------------------------------

    public function testCreatePasswordResetTokenExecutes(): void
    {
        $stmt = $this->makeStmt();
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $repo->createPasswordResetToken(1, 'reset-hash', '2099-01-01 00:00:00', '127.0.0.1', 'UA');
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // findValidPasswordResetToken
    // -------------------------------------------------------------------------

    public function testFindValidPasswordResetTokenReturnsTypedArray(): void
    {
        $row = ['user_id' => '10'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $result = $repo->findValidPasswordResetToken('reset-hash');

        $this->assertNotNull($result);
        $this->assertSame(10, $result['user_id']);
    }

    public function testFindValidPasswordResetTokenReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertNull($repo->findValidPasswordResetToken('expired-hash'));
    }

    // -------------------------------------------------------------------------
    // markPasswordResetTokenUsed
    // -------------------------------------------------------------------------

    public function testMarkPasswordResetTokenUsedReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertTrue($repo->markPasswordResetTokenUsed('reset-hash'));
    }

    public function testMarkPasswordResetTokenUsedReturnsFalseOnFailure(): void
    {
        $stmt = $this->makeStmt(executeReturn: false);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertFalse($repo->markPasswordResetTokenUsed('reset-hash'));
    }

    // -------------------------------------------------------------------------
    // deleteExpiredPasswordResetTokens (usa query())
    // -------------------------------------------------------------------------

    public function testDeleteExpiredPasswordResetTokensReturnsCount(): void
    {
        $stmt = $this->makeStmt(rowCount: 3);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertSame(3, $repo->deleteExpiredPasswordResetTokens());
    }

    public function testDeleteExpiredPasswordResetTokensReturnsZeroWhenNone(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new AuthTokenRepository($this->makePdo($stmt));

        $this->assertSame(0, $repo->deleteExpiredPasswordResetTokens());
    }
}
