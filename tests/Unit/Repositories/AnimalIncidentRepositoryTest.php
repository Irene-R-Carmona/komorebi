<?php

/**
 * ¿Qué pruebas aquí? AnimalIncidentRepository: getActiveIncidents, findById,
 *   create, resolve.
 * ¿Qué me quieres demostrar? Que getActiveIncidents usa query() (no prepare),
 *   que create retorna (int)lastInsertId(), que findById retorna null cuando
 *   fetch() es false, y que resolve retorna el bool de execute().
 * ¿Qué va a fallar en este test si se cambia el código? Si getActiveIncidents
 *   pasa de query() a prepare(), si create deja de usar lastInsertId(), o si
 *   resolve deja de devolver el resultado de execute().
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\AnimalIncidentRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnimalIncidentRepository::class)]
final class AnimalIncidentRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
        bool $executeReturn = true,
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);

        return $stmt;
    }

    private function makePdo(PDOStatement $stmt, string $lastInsertId = '1'): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn($lastInsertId);

        return $pdo;
    }

    // -------------------------------------------------------------------------
    // getActiveIncidents (usa query())
    // -------------------------------------------------------------------------

    public function testGetActiveIncidentsReturnsRows(): void
    {
        $rows = [['id' => 1, 'animal_name' => 'Hachi', 'severity' => 'high']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new AnimalIncidentRepository($this->makePdo($stmt));

        $result = $repo->getActiveIncidents();
        $this->assertCount(1, $result);
        $this->assertSame('high', $result[0]['severity']);
    }

    public function testGetActiveIncidentsReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new AnimalIncidentRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->getActiveIncidents());
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = [
            'id' => 3,
            'animal_id' => 2,
            'incident_type' => 'injury',
            'description' => 'Scratch on paw',
            'severity' => 'medium',
            'created_at' => '2024-01-01 00:00:00',
            'animal_name' => 'Luna',
        ];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new AnimalIncidentRepository($this->makePdo($stmt));

        $result = $repo->findById(3);
        $this->assertNotNull($result);
        $this->assertSame(3, $result->id);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new AnimalIncidentRepository($this->makePdo($stmt));

        $this->assertNull($repo->findById(999));
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function testCreateReturnsInsertedId(): void
    {
        $stmt = $this->makeStmt();
        $repo = new AnimalIncidentRepository($this->makePdo($stmt, '8'));

        $id = $repo->create([
            'animal_id' => 2,
            'severity' => 'low',
            'description' => 'El animal cojea ligeramente.',
            'reported_by_user_id' => 5,
        ]);

        $this->assertSame(8, $id);
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new AnimalIncidentRepository($this->makePdo($stmt));

        $this->assertTrue($repo->resolve(3, 'Sin gravedad.', 1));
    }

    public function testResolveReturnsFalseOnFailure(): void
    {
        $stmt = $this->makeStmt(executeReturn: false);
        $repo = new AnimalIncidentRepository($this->makePdo($stmt));

        $this->assertFalse($repo->resolve(999, null, null));
    }
}
