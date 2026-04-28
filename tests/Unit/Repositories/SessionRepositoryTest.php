<?php

/**
 * ¿Qué pruebas aquí? SessionRepository: createOrUpdate, findActiveByUserId,
 *   findById, updateActivity, revoke, revokeAllExcept, deleteExpired.
 * ¿Qué me quieres demostrar? Que cada método usa la query correcta y transforma
 *   el resultado del PDOStatement de forma adecuada.
 * ¿Qué va a fallar en este test si se cambia el código? Si createOrUpdate deja de
 *   retornar $stmt->execute(), si deleteExpired usa prepare() en vez de query(), o
 *   si revokeAllExcept retorna bool en vez del rowCount().
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\SessionRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionRepository::class)]
final class SessionRepositoryTest extends TestCase
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

    /** PDO stub que sirve para prepare() y para query() (usado en deleteExpired). */
    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // createOrUpdate
    // -------------------------------------------------------------------------

    public function testCreateOrUpdateReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new SessionRepository($this->makePdo($stmt));

        $result = $repo->createOrUpdate(1, 'sess-abc', '127.0.0.1', 'UA', 'PC', 'now', 'later');

        $this->assertTrue($result);
    }

    public function testCreateOrUpdateReturnsFalseOnFailure(): void
    {
        $stmt = $this->makeStmt(executeReturn: false);
        $repo = new SessionRepository($this->makePdo($stmt));

        $result = $repo->createOrUpdate(1, 'sess-abc', '127.0.0.1', null, null, 'now', 'later');

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // findActiveByUserId
    // -------------------------------------------------------------------------

    public function testFindActiveByUserIdReturnsRows(): void
    {
        $rows = [['id' => 1, 'session_id' => 'abc', 'device_name' => 'Chrome']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new SessionRepository($this->makePdo($stmt));

        $result = $repo->findActiveByUserId(5);
        $this->assertCount(1, $result);
        $this->assertSame('abc', $result[0]['session_id']);
    }

    public function testFindActiveByUserIdReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findActiveByUserId(999));
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'user_id' => 10, 'session_id' => 'xyz'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertSame($row, $repo->findById(1));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertNull($repo->findById(99));
    }

    // -------------------------------------------------------------------------
    // updateActivity
    // -------------------------------------------------------------------------

    public function testUpdateActivityReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertTrue($repo->updateActivity('sess-abc'));
    }

    public function testUpdateActivityReturnsFalseOnFailure(): void
    {
        $stmt = $this->makeStmt(executeReturn: false);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertFalse($repo->updateActivity('sess-abc'));
    }

    // -------------------------------------------------------------------------
    // revoke
    // -------------------------------------------------------------------------

    public function testRevokeReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertTrue($repo->revoke(1, 10, 'manual'));
    }

    public function testRevokeReturnsFalseOnFailure(): void
    {
        $stmt = $this->makeStmt(executeReturn: false);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertFalse($repo->revoke(1, 10, 'manual'));
    }

    // -------------------------------------------------------------------------
    // revokeAllExcept
    // -------------------------------------------------------------------------

    public function testRevokeAllExceptReturnsRowCount(): void
    {
        $stmt = $this->makeStmt(rowCount: 3);
        $repo = new SessionRepository($this->makePdo($stmt));

        $count = $repo->revokeAllExcept(5, 'current-session-id', 5);
        $this->assertSame(3, $count);
    }

    public function testRevokeAllExceptReturnsZeroWhenNothingRevoked(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertSame(0, $repo->revokeAllExcept(5, 'current-session-id', 5));
    }

    // -------------------------------------------------------------------------
    // deleteExpired (usa $this->db->query() no prepare())
    // -------------------------------------------------------------------------

    public function testDeleteExpiredReturnsCountOfDeleted(): void
    {
        $stmt = $this->makeStmt(rowCount: 7);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertSame(7, $repo->deleteExpired());
    }

    public function testDeleteExpiredReturnsZeroWhenNothingDeleted(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new SessionRepository($this->makePdo($stmt));

        $this->assertSame(0, $repo->deleteExpired());
    }
}
