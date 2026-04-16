<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Repositories;

use App\Repositories\AnimalRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests para AnimalRepository
 *
 * Verifica acceso a datos de animales con prepared statements
 */
final class AnimalRepositoryTest extends TestCase
{
    private AnimalRepository $repository;

    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = $this->createStub(PDO::class);
        $this->repository = new AnimalRepository($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->db);
    }

    public function testRepositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AnimalRepository::class, $this->repository);
    }

    public function testFindByIdReturnsAnimal(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'cafe_id' => 2,
            'name' => 'Luna',
            'species_type' => 'cat',
            'current_status' => 'active',
            'age' => 3,
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findById(1);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('Luna', $result['name']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function testFindActiveByCafeReturnsArray(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Luna', 'species_type' => 'cat', 'current_status' => 'active'],
            ['id' => 2, 'name' => 'Max', 'species_type' => 'dog', 'current_status' => 'active'],
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findActiveByCafe(2);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Luna', $result[0]['name']);
    }

    public function testFindActiveByCafeReturnsEmptyArrayWhenNone(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findActiveByCafe(999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testIsAvailableReturnsTrueWhenActive(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'current_status' => 'active',
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->isAvailable(1);

        $this->assertTrue($result);
    }

    public function testIsAvailableReturnsFalseWhenResting(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'current_status' => 'resting',
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->isAvailable(1);

        $this->assertFalse($result);
    }

    public function testIsAvailableReturnsFalseWhenNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->isAvailable(999);

        $this->assertFalse($result);
    }

    public function testIsRestingReturnsTrueWhenStatusResting(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'current_status' => 'resting',
            'last_check_at' => '2026-02-20 10:00:00',
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->isResting(1);

        $this->assertTrue($result);
    }

    public function testIsRestingReturnsFalseWhenActive(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'current_status' => 'active',
            'last_check_at' => '2026-02-20 10:00:00',
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->isResting(1);

        $this->assertFalse($result);
    }
}
