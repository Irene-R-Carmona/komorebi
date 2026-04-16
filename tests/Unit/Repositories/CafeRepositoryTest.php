<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Repositories;

use App\Repositories\CafeRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests para CafeRepository
 *
 * Valida la capa de acceso a datos de cafés con mocks de PDO.
 */
final class CafeRepositoryTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\PDO */
    private PDO $pdoMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\PDOStatement */
    private PDOStatement $stmtMock;
    private CafeRepository $repository;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->repository = new CafeRepository($this->pdoMock);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->pdoMock, $this->stmtMock);
    }

    public function testFindByIdReturnsCafe(): void
    {
        $expectedData = [
            'id' => 1,
            'name' => 'Komorebi Shibuya',
            'slug' => 'komorebi-shibuya',
            'location' => 'Shibuya, Tokyo',
            'category' => 'cat',
            'is_active' => 1,
            'capacity_max' => 30,
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findById(1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Komorebi Shibuya', $result['name']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function testFindActiveReturnsOnlyActiveCafes(): void
    {
        $expectedData = [
            ['id' => 1, 'name' => 'Cafe 1', 'is_active' => 1],
            ['id' => 2, 'name' => 'Cafe 2', 'is_active' => 1],
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('query')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findActive();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['is_active']);
    }

    public function testFindByCategoryReturnsCafesOfCategory(): void
    {
        $expectedData = [
            ['id' => 1, 'name' => 'Cat Cafe 1', 'category' => 'cat'],
            ['id' => 3, 'name' => 'Cat Cafe 2', 'category' => 'cat'],
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['category' => 'cat'])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findByCategory('cat');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('cat', $result[0]['category']);
    }

    public function testCreateInsertsCafe(): void
    {
        $cafeData = [
            'name' => 'New Cafe',
            'slug' => 'new-cafe',
            'location' => 'Shinjuku, Tokyo',
            'category' => 'dog',
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $this->pdoMock
            ->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('10');

        $result = $this->repository->create($cafeData);

        $this->assertEquals(10, $result);
    }

    public function testUpdateModifiesCafe(): void
    {
        $updateData = [
            'name' => 'Updated Name',
            'capacity_max' => 40,
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->update(1, $updateData);

        $this->assertTrue($result);
    }

    public function testDeleteSoftDeletesCafe(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->delete(1);

        $this->assertTrue($result);
    }

    public function testFindFilteredAppliesMultipleFilters(): void
    {
        $expectedData = [
            ['id' => 2, 'name' => 'Filtered Cafe', 'category' => 'cat', 'is_active' => 1],
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $filters = ['category' => 'cat', 'is_active' => 1];
        $result = $this->repository->findFiltered($filters);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testFindAvailableForReservationFiltersCorrectly(): void
    {
        $expectedData = [
            ['id' => 1, 'name' => 'Café Neko', 'slug' => 'neko', 'is_active' => 1, 'has_reservations' => 1],
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('query')
            ->with($this->stringContains('has_reservations = 1'))
            ->willReturn($this->stmtMock);

        $result = $this->repository->findAvailableForReservation();

        $this->assertCount(1, $result);
        $this->assertSame('Café Neko', $result[0]['name']);
    }

    public function testFindAvailableForReservationByIdIndexesById(): void
    {
        $expectedData = [
            ['id' => 3, 'name' => 'Café Inu', 'slug' => 'inu'],
            ['id' => 7, 'name' => 'Café Neko', 'slug' => 'neko'],
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('query')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findAvailableForReservationById();

        $this->assertArrayHasKey(3, $result);
        $this->assertArrayHasKey(7, $result);
        $this->assertSame('Café Inu', $result[3]['name']);
        $this->assertSame('Café Neko', $result[7]['name']);
    }

    public function testExistsAndActiveReturnsTrueWhenFound(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(['id' => 1]);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $this->assertTrue($this->repository->existsAndActive(1));
    }

    public function testExistsAndActiveReturnsFalseWhenNotFound(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $this->assertFalse($this->repository->existsAndActive(999));
    }
}
