<?php

/**
 * ¿Qué pruebas aquí? ApiTokenRepository: findByHash, findByIdForUser, createToken,
 *   revoke, listForUser, updateLastUsed.
 * ¿Qué me quieres demostrar? Que findByHash y findByIdForUser retornan null cuando
 *   fetch() devuelve false; que createToken retorna el ID del lastInsertId(); que
 *   revoke retorna bool según rowCount() > 0.
 * ¿Qué va a fallar en este test si se cambia el código? Si findByHash deja de
 *   retornar null para fetch()=false, si revoke invierte la comparación rowCount(),
 *   o si createToken deja de castear lastInsertId() a int.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ApiTokenRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiTokenRepository::class)]
final class ApiTokenRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
        bool $executeReturn = true,
        int $rowCount = 0,
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('rowCount')->willReturn($rowCount);
        return $stmt;
    }

    private function makePdo(PDOStatement $stmt, string $lastInsertId = '10'): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn($lastInsertId);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // findByHash
    // -------------------------------------------------------------------------

    public function testFindByHashReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'user_id' => 5, 'name' => 'API Key'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $result = $repo->findByHash('sha256hashvalue');
        $this->assertSame($row, $result);
    }

    public function testFindByHashReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $this->assertNull($repo->findByHash('invalid-hash'));
    }

    // -------------------------------------------------------------------------
    // findByIdForUser
    // -------------------------------------------------------------------------

    public function testFindByIdForUserReturnsArrayWhenFound(): void
    {
        $row = ['id' => 3, 'user_id' => 5, 'name' => 'My Token'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $result = $repo->findByIdForUser(3, 5);
        $this->assertSame($row, $result);
    }

    public function testFindByIdForUserReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $this->assertNull($repo->findByIdForUser(99, 5));
    }

    // -------------------------------------------------------------------------
    // createToken
    // -------------------------------------------------------------------------

    public function testCreateTokenReturnsInsertedId(): void
    {
        $stmt = $this->makeStmt();
        $repo = new ApiTokenRepository($this->makePdo($stmt, '42'));

        $id = $repo->createToken(5, 'CI Key', 'sha256hashvalue');
        $this->assertSame(42, $id);
    }

    public function testCreateTokenWithExpiresAtReturnsInsertedId(): void
    {
        $stmt = $this->makeStmt();
        $repo = new ApiTokenRepository($this->makePdo($stmt, '7'));

        $expiresAt = new DateTimeImmutable('+1 year');
        $id = $repo->createToken(5, 'Expiring Key', 'sha256hashvalue', $expiresAt);
        $this->assertSame(7, $id);
    }

    // -------------------------------------------------------------------------
    // revoke
    // -------------------------------------------------------------------------

    public function testRevokeReturnsTrueWhenRowUpdated(): void
    {
        $stmt = $this->makeStmt(rowCount: 1);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $this->assertTrue($repo->revoke(1, 5));
    }

    public function testRevokeReturnsFalseWhenNoRowUpdated(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $this->assertFalse($repo->revoke(99, 5));
    }

    // -------------------------------------------------------------------------
    // listForUser
    // -------------------------------------------------------------------------

    public function testListForUserReturnsRows(): void
    {
        $rows = [['id' => 1, 'name' => 'Key 1'], ['id' => 2, 'name' => 'Key 2']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $result = $repo->listForUser(5);
        $this->assertCount(2, $result);
    }

    public function testListForUserReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->listForUser(5));
    }

    // -------------------------------------------------------------------------
    // updateLastUsed — void
    // -------------------------------------------------------------------------

    public function testUpdateLastUsedExecutes(): void
    {
        $stmt = $this->makeStmt();
        $repo = new ApiTokenRepository($this->makePdo($stmt));

        $repo->updateLastUsed(1);
        $this->addToAssertionCount(1);
    }
}
