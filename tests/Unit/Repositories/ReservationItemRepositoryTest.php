<?php

/**
 * ¿Qué pruebas aquí? ReservationItemRepository: findByReservation,
 *   findPendingByStation, findAllPendingByCafe, findCompletedToday, add,
 *   updateStatus, markReady, markServed, bumpTicket, getDailyStats,
 *   getEstimatedWaitTime.
 * ¿Qué me quieres demostrar? Que add retorna (int)lastInsertId(), que bumpTicket
 *   retorna rowCount(), que getDailyStats retorna el array de fallback cuando
 *   fetch() es false, y que getEstimatedWaitTime retorna (int)fetchColumn().
 * ¿Qué va a fallar en este test si se cambia el código? Si add deja de usar
 *   lastInsertId(), si bumpTicket deja de retornar rowCount(), o si getDailyStats
 *   deja de retornar el array de fallback cuando no hay filas.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ReservationItemRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationItemRepository::class)]
final class ReservationItemRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
        bool $executeReturn = true,
        int $rowCount = 0,
        mixed $fetchColumnReturn = false,
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('rowCount')->willReturn($rowCount);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);

        return $stmt;
    }

    private function makePdo(PDOStatement $stmt, string $lastInsertId = '5'): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn($lastInsertId);

        return $pdo;
    }

    // -------------------------------------------------------------------------
    // findByReservation
    // -------------------------------------------------------------------------

    public function testFindByReservationReturnsRows(): void
    {
        $rows = [['id' => 1, 'product_name' => 'Matcha Latte', 'quantity' => 2, 'status' => 'pending']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $result = $repo->findByReservation(10);
        $this->assertCount(1, $result);
        $this->assertSame('Matcha Latte', $result[0]['product_name']); // @phpstan-ignore offsetAccess.notFound
    }

    public function testFindByReservationReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findByReservation(999));
    }

    // -------------------------------------------------------------------------
    // findPendingByStation
    // -------------------------------------------------------------------------

    public function testFindPendingByStationReturnsRows(): void
    {
        $rows = [['id' => 2, 'product_name' => 'Croissant', 'status' => 'kitchen']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $result = $repo->findPendingByStation(1, 'bakery');
        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // findAllPendingByCafe
    // -------------------------------------------------------------------------

    public function testFindAllPendingByCafeReturnsRows(): void
    {
        $rows = [['id' => 3, 'product_name' => 'Espresso', 'status' => 'pending']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $result = $repo->findAllPendingByCafe(1);
        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // findCompletedToday
    // -------------------------------------------------------------------------

    public function testFindCompletedTodayReturnsRows(): void
    {
        $rows = [['id' => 4, 'product_name' => 'Smoothie', 'status' => 'served']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $result = $repo->findCompletedToday(1);
        $this->assertCount(1, $result);
        $this->assertSame('served', $result[0]['status']);
    }

    // -------------------------------------------------------------------------
    // add (devuelve (int)lastInsertId())
    // -------------------------------------------------------------------------

    public function testAddReturnsInsertedId(): void
    {
        $stmt = $this->makeStmt();
        $repo = new ReservationItemRepository($this->makePdo($stmt, '12'));

        $id = $repo->add(10, 3, 2, 5.50);
        $this->assertSame(12, $id);
    }

    // -------------------------------------------------------------------------
    // updateStatus
    // -------------------------------------------------------------------------

    public function testUpdateStatusReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertTrue($repo->updateStatus(1, 'kitchen'));
    }

    public function testUpdateStatusReturnsFalseOnFailure(): void
    {
        $stmt = $this->makeStmt(executeReturn: false);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertFalse($repo->updateStatus(999, 'served'));
    }

    // -------------------------------------------------------------------------
    // markReady (delega a updateStatus)
    // -------------------------------------------------------------------------

    public function testMarkReadyReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertTrue($repo->markReady(1));
    }

    // -------------------------------------------------------------------------
    // markServed (delega a updateStatus)
    // -------------------------------------------------------------------------

    public function testMarkServedReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertTrue($repo->markServed(1));
    }

    // -------------------------------------------------------------------------
    // bumpTicket (devuelve rowCount())
    // -------------------------------------------------------------------------

    public function testBumpTicketReturnsUpdatedCount(): void
    {
        $stmt = $this->makeStmt(rowCount: 3);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertSame(3, $repo->bumpTicket(10));
    }

    public function testBumpTicketReturnsZeroWhenNothingUpdated(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertSame(0, $repo->bumpTicket(10));
    }

    // -------------------------------------------------------------------------
    // getDailyStats
    // -------------------------------------------------------------------------

    public function testGetDailyStatsReturnsStatsRow(): void
    {
        $row = ['pending' => '2', 'in_progress' => '1', 'ready' => '3', 'served' => '10', 'avg_prep_time' => '5.0'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $result = $repo->getDailyStats(1);
        $this->assertSame('2', $result['pending']);
        $this->assertSame('10', $result['served']);
    }

    public function testGetDailyStatsReturnsFallbackWhenEmpty(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $result = $repo->getDailyStats(1);
        $this->assertSame(0, $result['pending']);
        $this->assertSame(0, $result['in_progress']);
        $this->assertSame(0, $result['ready']);
        $this->assertSame(0, $result['served']);
        $this->assertNull($result['avg_prep_time']);
    }

    // -------------------------------------------------------------------------
    // getEstimatedWaitTime
    // -------------------------------------------------------------------------

    public function testGetEstimatedWaitTimeReturnsInt(): void
    {
        $stmt = $this->makeStmt(fetchColumnReturn: '15');
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertSame(15, $repo->getEstimatedWaitTime(1));
    }

    public function testGetEstimatedWaitTimeReturnsZeroWhenNull(): void
    {
        $stmt = $this->makeStmt(fetchColumnReturn: false);
        $repo = new ReservationItemRepository($this->makePdo($stmt));

        $this->assertSame(0, $repo->getEstimatedWaitTime(1));
    }
}
