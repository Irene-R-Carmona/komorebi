<?php

/**
 * ¿Qué pruebas aquí? StaffShiftRepository: findByCafeAndDateRange, findRecentByUserAndCafe,
 *   hasOverlap, getPerformanceMetrics (2 queries) y el findById heredado de AbstractRepository.
 * ¿Qué me quieres demostrar? Que los métodos delegan a PDO correctamente y transforman
 *   los resultados del statement de forma adecuada.
 * ¿Qué va a fallar en este test si se cambia el código? Si hasOverlap deja de hacer (bool)
 *   del fetch, si getPerformanceMetrics cambia el cálculo de avg_shift_duration, o si
 *   findById deja de retornar null cuando fetch() es false.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\StaffShiftDTO;
use App\Repositories\StaffShiftRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StaffShiftRepository::class)]
final class StaffShiftRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
        bool $executeReturn = true,
        int $rowCount = 0,
        string $lastInsertId = '1',
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('rowCount')->willReturn($rowCount);
        $stmt->method('bindValue')->willReturn(true);
        return $stmt;
    }

    private function makePdo(PDOStatement $stmt, string $lastInsertId = '1'): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn($lastInsertId);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // findById (heredado de AbstractRepository)
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = [
            'id'         => 1,
            'user_id'    => 5,
            'cafe_id'    => 1,
            'shift_date' => '2026-01-01',
            'shift_start' => '08:00:00',
            'shift_end'   => '16:00:00',
            'notes'       => null,
            'created_by'  => null,
            'created_at'  => '2026-01-01 00:00:00',
            'updated_at'  => '2026-01-01 00:00:00',
        ];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $result = $repo->findById(1);
        $this->assertNotNull($result);
        $this->assertSame(1, $result->id);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $this->assertNull($repo->findById(99));
    }

    // -------------------------------------------------------------------------
    // findByCafeAndDateRange
    // -------------------------------------------------------------------------

    public function testFindByCafeAndDateRangeReturnsRows(): void
    {
        $rows = [['id' => 1, 'shift_date' => '2026-01-01', 'staff_name' => 'Ana']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $result = $repo->findByCafeAndDateRange(1, '2026-01-01', '2026-01-07');
        $this->assertCount(1, $result);
        $this->assertSame('Ana', $result[0]['staff_name']);
    }

    public function testFindByCafeAndDateRangeReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findByCafeAndDateRange(1, '2026-01-01', '2026-01-07'));
    }

    // -------------------------------------------------------------------------
    // findRecentByUserAndCafe
    // -------------------------------------------------------------------------

    public function testFindRecentByUserAndCafeReturnsRows(): void
    {
        $rows = [['id' => 2, 'shift_date' => '2025-12-30']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $result = $repo->findRecentByUserAndCafe(3, 1);
        $this->assertCount(1, $result);
    }

    public function testFindRecentByUserAndCafeWithCustomLimitReturnsRows(): void
    {
        $rows = [['id' => 3]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $result = $repo->findRecentByUserAndCafe(3, 1, 10);
        $this->assertCount(1, $result);
    }

    public function testFindRecentByUserAndCafeReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findRecentByUserAndCafe(999, 1));
    }

    // -------------------------------------------------------------------------
    // hasOverlap
    // -------------------------------------------------------------------------

    public function testHasOverlapReturnsTrueWhenConflictExists(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['id' => 5]);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $this->assertTrue($repo->hasOverlap(3, '2026-01-01', '09:00', '17:00'));
    }

    public function testHasOverlapReturnsFalseWhenNoConflict(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $this->assertFalse($repo->hasOverlap(3, '2026-01-01', '09:00', '17:00'));
    }

    // -------------------------------------------------------------------------
    // getPerformanceMetrics (2 separate prepare() calls, both return same stub)
    // -------------------------------------------------------------------------

    public function testGetPerformanceMetricsReturnsComputedArray(): void
    {
        // El mismo stub retorna el mismo array en ambos fetch() — las claves
        // extra que no usa cada query se ignoran.
        $row = ['total_shifts' => 4, 'total_hours' => '32', 'shifts_this_month' => 2];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $metrics = $repo->getPerformanceMetrics(3, 1);

        $this->assertSame(4, $metrics['total_shifts']);
        $this->assertSame(32.0, $metrics['total_hours']);
        $this->assertSame(2, $metrics['shifts_this_month']);
        $this->assertSame(8.0, $metrics['avg_shift_duration']);
    }

    public function testGetPerformanceMetricsReturnsZeroAvgWhenNoShifts(): void
    {
        $row = ['total_shifts' => 0, 'total_hours' => '0', 'shifts_this_month' => 0];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $metrics = $repo->getPerformanceMetrics(3, 1);

        $this->assertSame(0, $metrics['total_shifts']);
        $this->assertSame(0.0, $metrics['avg_shift_duration']);
    }

    public function testGetPerformanceMetricsHandlesFalseRows(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new StaffShiftRepository($this->makePdo($stmt));

        $metrics = $repo->getPerformanceMetrics(3, 1);

        $this->assertSame(0, $metrics['total_shifts']);
        $this->assertSame(0.0, $metrics['total_hours']);
        $this->assertSame(0, $metrics['shifts_this_month']);
        $this->assertSame(0.0, $metrics['avg_shift_duration']);
    }
}
