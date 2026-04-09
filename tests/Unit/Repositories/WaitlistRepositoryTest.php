<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
namespace Repositories;

use App\Repositories\WaitlistRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests para WaitlistRepository
 */
final class WaitlistRepositoryTest extends TestCase
{
    private WaitlistRepository $repository;

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = $this->createStub(PDO::class);
        $this->repository = new WaitlistRepository($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->db);
    }

    public function testRepositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(WaitlistRepository::class, $this->repository);
    }

    public function testFindByIdReturnsWaitlistEntry(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 1,
            'user_id' => 5,
            'time_slot_id' => 10,
            'position' => 3,
            'status' => 'waiting',
            'reservation_date' => '2026-02-20',
            'reservation_time' => '14:00:00',
            'cafe_name' => 'Komorebi Café',
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findById(1);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame(3, $result['position']);
    }

    public function testGetPositionReturnsInt(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['position' => 5]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->getPosition(10, 5);

        $this->assertIsInt($result);
        $this->assertSame(5, $result);
    }

    public function testGetPositionReturnsNullWhenNotInList(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->getPosition(10, 999);

        $this->assertNull($result);
    }

    public function testFindActiveByUserIdReturnsArray(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'position' => 2, 'status' => 'waiting'],
            ['id' => 2, 'position' => 5, 'status' => 'promoted'],
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->findActiveByUserId(5);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testUserInWaitlistReturnsBool(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['1' => 1]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->repository->userInWaitlist(5, 10);

        $this->assertTrue($result);
    }
}
