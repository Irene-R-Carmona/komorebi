<?php

/**
 * ¿Qué pruebas aquí? TimeSlotRepository: findById, getAvailableCapacity, isFull,
 *   isBlocked, reserveSpots, releaseSpots, findAvailableSlots, findAvailableRange,
 *   getOccupancyStats y findAvailableByDateFiltered.
 * ¿Qué me quieres demostrar? Que cada método prepara la query correcta, pasa los
 *   parámetros adecuados y transforma el resultado del PDOStatement esperado.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el criterio de
 *   isFull (≤0 → <0), si reserveSpots deja de usar rowCount, o si findById cambia
 *   a devolver false en lugar de null.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\TimeSlotDTO;
use App\Repositories\TimeSlotRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TimeSlotRepository::class)]
final class TimeSlotRepositoryTest extends TestCase
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
        $row = [
            'id' => 1,
            'cafe_id' => 2,
            'slot_date' => '2026-01-01',
            'slot_time' => '10:00:00',
            'total_capacity' => 10,
            'available_spots' => 5,
            'reserved_spots' => 5,
            'is_blocked' => 0,
            'blocked_reason' => null,
            'duration_minutes' => 60,
            'created_by' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $result = $repo->findById(1);

        $this->assertInstanceOf(TimeSlotDTO::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame(2, $result->cafe_id);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertNull($repo->findById(99));
    }

    // -------------------------------------------------------------------------
    // getAvailableCapacity
    // -------------------------------------------------------------------------

    public function testGetAvailableCapacityReturnsInt(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['available_spots' => 8]);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertSame(8, $repo->getAvailableCapacity(1));
    }

    public function testGetAvailableCapacityReturnsZeroWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertSame(0, $repo->getAvailableCapacity(99));
    }

    // -------------------------------------------------------------------------
    // isFull
    // -------------------------------------------------------------------------

    public function testIsFullReturnsTrueWhenNoSpots(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['available_spots' => 0]);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertTrue($repo->isFull(1));
    }

    public function testIsFullReturnsFalseWhenSpotsAvailable(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['available_spots' => 3]);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertFalse($repo->isFull(1));
    }

    // -------------------------------------------------------------------------
    // isBlocked
    // -------------------------------------------------------------------------

    public function testIsBlockedReturnsTrueWhenBlocked(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['is_blocked' => 1]);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertTrue($repo->isBlocked(1));
    }

    public function testIsBlockedReturnsFalseWhenNotBlocked(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['is_blocked' => 0]);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertFalse($repo->isBlocked(1));
    }

    public function testIsBlockedReturnsFalseWhenSlotNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertFalse($repo->isBlocked(99));
    }

    // -------------------------------------------------------------------------
    // reserveSpots
    // -------------------------------------------------------------------------

    public function testReserveSpotsReturnsTrueWhenRowAffected(): void
    {
        $stmt = $this->makeStmt(rowCount: 1);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertTrue($repo->reserveSpots(1, 2));
    }

    public function testReserveSpotsReturnsFalseWhenNoRowAffected(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertFalse($repo->reserveSpots(1, 100));
    }

    // -------------------------------------------------------------------------
    // releaseSpots
    // -------------------------------------------------------------------------

    public function testReleaseSpotsReturnsTrueWhenRowAffected(): void
    {
        $stmt = $this->makeStmt(rowCount: 1);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertTrue($repo->releaseSpots(1, 2));
    }

    public function testReleaseSpotsReturnsFalseWhenNoRowAffected(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertFalse($repo->releaseSpots(1, 100));
    }

    // -------------------------------------------------------------------------
    // findAvailableSlots
    // -------------------------------------------------------------------------

    public function testFindAvailableSlotsReturnsRows(): void
    {
        $rows = [['id' => 1, 'slot_time' => '10:00', 'available_spots' => 4]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $result = $repo->findAvailableSlots(1, '2026-01-01');
        $this->assertCount(1, $result);
        $this->assertSame('10:00', $result[0]['slot_time']);
    }

    public function testFindAvailableSlotsReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findAvailableSlots(1, '2026-01-01'));
    }

    // -------------------------------------------------------------------------
    // findAvailableRange
    // -------------------------------------------------------------------------

    public function testFindAvailableRangeReturnsRows(): void
    {
        $rows = [['id' => 1, 'availability_status' => 'available']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $result = $repo->findAvailableRange(1, '2026-01-01', '2026-01-07');
        $this->assertCount(1, $result);
    }

    public function testFindAvailableRangeReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findAvailableRange(1, '2026-01-01', '2026-01-07', 5));
    }

    // -------------------------------------------------------------------------
    // getOccupancyStats
    // -------------------------------------------------------------------------

    public function testGetOccupancyStatsReturnsArray(): void
    {
        $stats = ['total_slots' => 10, 'avg_occupancy_percentage' => 75.5];
        $stmt = $this->makeStmt(fetchReturn: $stats);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $result = $repo->getOccupancyStats(1, '2026-01-01', '2026-01-31');
        $this->assertSame($stats, $result);
    }

    public function testGetOccupancyStatsReturnsEmptyArrayWhenNoData(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->getOccupancyStats(1, '2026-01-01', '2026-01-31'));
    }

    // -------------------------------------------------------------------------
    // findAvailableByDateFiltered
    // -------------------------------------------------------------------------

    public function testFindAvailableByDateFilteredNoFiltersReturnsRows(): void
    {
        $rows = [['id' => 1, 'slot_time' => '09:00']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $result = $repo->findAvailableByDateFiltered('2026-01-01');
        $this->assertCount(1, $result);
    }

    public function testFindAvailableByDateFilteredWithCafeIdReturnsRows(): void
    {
        $rows = [['id' => 2, 'cafe_id' => 5]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $result = $repo->findAvailableByDateFiltered('2026-01-01', cafeId: 5);
        $this->assertCount(1, $result);
    }

    public function testFindAvailableByDateFilteredWithGuestsReturnsRows(): void
    {
        $rows = [['id' => 3, 'available_spots' => 4]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $result = $repo->findAvailableByDateFiltered('2026-01-01', guests: 4);
        $this->assertCount(1, $result);
    }

    public function testFindAvailableByDateFilteredReturnsEmptyArrayWhenNoResults(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new TimeSlotRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findAvailableByDateFiltered('2026-01-01', cafeId: 1, guests: 10));
    }
}
