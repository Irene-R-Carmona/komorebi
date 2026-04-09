<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Repositories;

use App\Repositories\ReservationRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests para ReservationRepository.
 *
 * Demuestra cómo testear repositorios mockeando solo PDO,
 * sin necesidad de base de datos real.
 */
final class ReservationRepositoryTest extends TestCase
{
    private PDO&MockObject $pdoMock;
    private PDOStatement&MockObject $stmtMock;
    private ReservationRepository $repository;

    protected function setUp(): void
    {
        // Mock de PDO y PDOStatement
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);

        // Crear repositorio con PDO mockeado
        $this->repository = new ReservationRepository($this->pdoMock);
    }

    public function testFindByIdReturnsReservation(): void
    {
        $expectedData = [
            'id' => 1,
            'uuid' => 'abc-123',
            'user_id' => 5,
            'cafe_id' => 2,
            'status' => 'confirmed',
            'reservation_date' => '2026-02-10',
            'reservation_time' => '14:00:00',
            'guest_count' => 2,
        ];

        // Configurar mock
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

        // Ejecutar
        $result = $this->repository->findById(1);

        // Verificar
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('confirmed', $result['status']);
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

    public function testFindByUuidReturnsReservation(): void
    {
        $uuid = 'test-uuid-123';
        $expectedData = [
            'id' => 1,
            'uuid' => $uuid,
            'status' => 'pending',
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['uuid' => $uuid])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findByUuid($uuid);

        $this->assertIsArray($result);
        $this->assertEquals($uuid, $result['uuid']);
    }

    public function testFindActiveByUserReturnsArray(): void
    {
        $userId = 10;
        $expectedData = [
            ['id' => 1, 'status' => 'confirmed', 'user_id' => $userId],
            ['id' => 2, 'status' => 'pending', 'user_id' => $userId],
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['user_id' => $userId])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findActiveByUser($userId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testIsSlotAvailableReturnsTrue(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(0); // 0 reservas = disponible

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->isSlotAvailable(1, '2026-02-10', '14:00:00');

        $this->assertTrue($result);
    }

    public function testIsSlotAvailableReturnsFalseWhenOccupied(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(1); // 1 o más reservas = ocupado

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->isSlotAvailable(1, '2026-02-10', '14:00:00');

        $this->assertFalse($result);
    }

    public function testUpdateStatusReturnsTrue(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->updateStatus(1, 'completed');

        $this->assertTrue($result);
    }

    public function testCountByUserReturnsInteger(): void
    {
        $userId = 5;

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(3);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->countByUser($userId);

        $this->assertIsInt($result);
        $this->assertEquals(3, $result);
    }
}
