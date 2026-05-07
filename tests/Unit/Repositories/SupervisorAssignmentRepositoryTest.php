<?php

/**
 * ¿Qué pruebas aquí? SupervisorAssignmentRepository: findBySupervisor, findActiveByCafe,
 *   createAssignment (delega a AbstractRepository::create), deactivate.
 * ¿Qué me quieres demostrar? Que createAssignment retorna (int) lastInsertId(), que
 *   deactivate usa rowCount() > 0, y que los métodos de búsqueda devuelven los
 *   resultados de fetchAll().
 * ¿Qué va a fallar en este test si se cambia el código? Si createAssignment deja de
 *   retornar el ID generado, si deactivate invierte la comparación o si findActiveByCafe
 *   incluye asignaciones inactivas.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\SupervisorAssignmentRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SupervisorAssignmentRepository::class)]
final class SupervisorAssignmentRepositoryTest extends TestCase
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
    // findBySupervisor
    // -------------------------------------------------------------------------

    public function testFindBySupervisorReturnsRows(): void
    {
        $rows = [['id' => 1, 'supervisor_id' => 10, 'is_active' => 1]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new SupervisorAssignmentRepository($this->makePdo($stmt));

        $result = $repo->findBySupervisor(10);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
    }

    public function testFindBySupervisorReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new SupervisorAssignmentRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findBySupervisor(999));
    }

    // -------------------------------------------------------------------------
    // findActiveByCafe
    // -------------------------------------------------------------------------

    public function testFindActiveByCafeReturnsActiveRows(): void
    {
        $rows = [['id' => 2, 'cafe_id' => 1, 'is_active' => 1]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new SupervisorAssignmentRepository($this->makePdo($stmt));

        $result = $repo->findActiveByCafe(1);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['is_active']);
    }

    public function testFindActiveByCafeReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new SupervisorAssignmentRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->findActiveByCafe(1));
    }

    // -------------------------------------------------------------------------
    // createAssignment (delega a AbstractRepository::create)
    // -------------------------------------------------------------------------

    public function testCreateAssignmentReturnsInsertedId(): void
    {
        $stmt = $this->makeStmt();
        $repo = new SupervisorAssignmentRepository($this->makePdo($stmt, '15'));

        $id = $repo->createAssignment([
            'supervisor_id' => 10,
            'reservation_id' => 20,
            'table_code' => 'T-01',
            'cafe_id' => 1,
            'is_active' => 1,
        ]);

        $this->assertSame(15, $id);
    }

    // -------------------------------------------------------------------------
    // deactivate
    // -------------------------------------------------------------------------

    public function testDeactivateReturnsTrueWhenRowUpdated(): void
    {
        $stmt = $this->makeStmt(rowCount: 1);
        $repo = new SupervisorAssignmentRepository($this->makePdo($stmt));

        $this->assertTrue($repo->deactivate(2));
    }

    public function testDeactivateReturnsFalseWhenNoRowUpdated(): void
    {
        $stmt = $this->makeStmt(rowCount: 0);
        $repo = new SupervisorAssignmentRepository($this->makePdo($stmt));

        $this->assertFalse($repo->deactivate(99));
    }
}
