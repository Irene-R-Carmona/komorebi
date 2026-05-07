<?php

/**
 * ¿Qué pruebas aquí? TrackerRepository: findById, findByCode, findByCafe,
 *   findAvailable, assign (happy + RuntimeException), release, markLost, getStats.
 * ¿Qué me quieres demostrar? Que assign lanza RuntimeException cuando rowCount()=0,
 *   que getStats construye el array con acumulador while-loop, y que findByCode
 *   usa strtoupper/trim del código.
 * ¿Qué va a fallar en este test si se cambia el código? Si assign deja de lanzar
 *   RuntimeException en rowCount=0, si getStats no suma correctamente los totales,
 *   o si release deja de retornar bool.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\TrackerRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TrackerRepository::class)]
final class TrackerRepositoryTest extends TestCase
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

    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'cafe_id' => 1, 'code' => 'T-01', 'type' => 'table', 'status' => 'available', 'cafe_name' => 'Komorebi'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $result = $repo->findById(1);
        $this->assertNotNull($result);
        $this->assertSame('T-01', $result->code);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $this->assertNull($repo->findById(999));
    }

    // -------------------------------------------------------------------------
    // findByCode
    // -------------------------------------------------------------------------

    public function testFindByCodeReturnsArrayWhenFound(): void
    {
        $row = ['id' => 2, 'code' => 'T-02', 'status' => 'in_use'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $result = $repo->findByCode(1, 't-02'); // lowercase — debe normalizarse
        $this->assertNotNull($result);
        $this->assertSame('T-02', $result['code']);
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $this->assertNull($repo->findByCode(1, 'INEXISTENTE'));
    }

    // -------------------------------------------------------------------------
    // findByCafe
    // -------------------------------------------------------------------------

    public function testFindByCafeReturnsRows(): void
    {
        $rows = [['id' => 1, 'status' => 'available'], ['id' => 2, 'status' => 'in_use']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $result = $repo->findByCafe(1);
        $this->assertCount(2, $result);
    }

    public function testFindByCafeWithStatusFilterReturnsRows(): void
    {
        $rows = [['id' => 1, 'status' => 'available']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $result = $repo->findByCafe(1, 'available');
        $this->assertCount(1, $result);
        $this->assertSame('available', $result[0]['status']);
    }

    // -------------------------------------------------------------------------
    // findAvailable (delega a findByCafe con status 'available')
    // -------------------------------------------------------------------------

    public function testFindAvailableReturnsRows(): void
    {
        $rows = [['id' => 3, 'status' => 'available']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $result = $repo->findAvailable(1);
        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // assign
    // -------------------------------------------------------------------------

    public function testAssignReturnsTrueWhenAvailable(): void
    {
        $stmt = $this->makeStmt(rowCount: 1);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $this->assertTrue($repo->assign(1));
    }

    public function testAssignThrowsExceptionWhenNotAvailable(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $this->expectException(RuntimeException::class);
        $repo->assign(99);
    }

    // -------------------------------------------------------------------------
    // release
    // -------------------------------------------------------------------------

    public function testReleaseReturnsBool(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $this->assertTrue($repo->release(1));
    }

    // -------------------------------------------------------------------------
    // markLost
    // -------------------------------------------------------------------------

    public function testMarkLostReturnsBool(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new TrackerRepository($this->makePdo($stmt));

        $this->assertTrue($repo->markLost(1));
    }

    // -------------------------------------------------------------------------
    // getStats (while-loop fetch)
    // -------------------------------------------------------------------------

    public function testGetStatsAccumulatesCountsByStatus(): void
    {
        // fetch() devolverá 2 filas y luego false (fin del loop)
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                ['status' => 'available', 'count' => '4'],
                ['status' => 'in_use',    'count' => '2'],
                false,
            );
        $pdo = $this->makePdo($stmt);
        $repo = new TrackerRepository($pdo);

        $stats = $repo->getStats(1);

        $this->assertSame(4, $stats['available']);
        $this->assertSame(2, $stats['in_use']);
        $this->assertSame(0, $stats['lost']);
        $this->assertSame(6, $stats['total']);
    }

    public function testGetStatsReturnsZerosWhenEmpty(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        $pdo = $this->makePdo($stmt);
        $repo = new TrackerRepository($pdo);

        $stats = $repo->getStats(1);

        $this->assertSame(0, $stats['available']);
        $this->assertSame(0, $stats['in_use']);
        $this->assertSame(0, $stats['lost']);
        $this->assertSame(0, $stats['total']);
    }
}
