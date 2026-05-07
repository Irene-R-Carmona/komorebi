<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\TimeSlot;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class TimeSlotTest extends TestCase
{
    private function stubPdoWithPrepare(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    // ── findAvailable ─────────────────────────────────────────────

    public function testFindAvailableReturnsOkWithSlots(): void
    {
        $rows = [
            ['id' => 1, 'cafe_id' => 1, 'available_spots' => 10],
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->findAvailable(1, '2024-06-01', '2024-06-07');

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data);
    }

    public function testFindAvailableReturnsOkWithEmptySlots(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->findAvailable(1, '2024-06-01', '2024-06-07', 5);

        $this->assertTrue($result->ok);
        $this->assertSame([], $result->data);
    }

    public function testFindAvailableReturnsFail_OnPdoException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('DB error'));

        $result = new TimeSlot($pdo)->findAvailable(1, '2024-06-01', '2024-06-07');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al buscar slots', $result->error);
    }

    // ── findById ──────────────────────────────────────────────────

    public function testFindByIdReturnsOkWithSlotData(): void
    {
        $row = ['id' => 5, 'cafe_id' => 1, 'is_blocked' => false];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($row);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->findById(5);

        $this->assertTrue($result->ok);
        $this->assertSame(5, $result->data['id']);
    }

    public function testFindByIdReturnsOkNullWhenNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->findById(999);

        $this->assertTrue($result->ok);
        $this->assertNull($result->data);
    }

    // ── findByIdForUpdate ─────────────────────────────────────────

    public function testFindByIdForUpdateReturnsOkWithSlot(): void
    {
        $row = ['id' => 3, 'available_spots' => 5];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($row);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->findByIdForUpdate(3);

        $this->assertTrue($result->ok);
        $this->assertSame(3, $result->data['id']);
    }

    public function testFindByIdForUpdateReturnsFailWhenNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->findByIdForUpdate(999);

        $this->assertFalse($result->ok);
        $this->assertSame('Slot no encontrado', $result->error);
    }

    // ── hasAvailability ───────────────────────────────────────────

    public function testHasAvailabilityReturnsTrueWhenSpaceAndNotBlocked(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['has_space' => 1, 'is_blocked' => 0]);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->hasAvailability(1, 2);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testHasAvailabilityReturnsFalseWhenBlocked(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['has_space' => 1, 'is_blocked' => 1]);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->hasAvailability(1);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data);
    }

    public function testHasAvailabilityReturnsFalseWhenNoSpace(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['has_space' => 0, 'is_blocked' => 0]);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->hasAvailability(1, 5);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data);
    }

    public function testHasAvailabilityReturnsFailWhenSlotNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->hasAvailability(999);

        $this->assertFalse($result->ok);
    }

    // ── decrementSpots ────────────────────────────────────────────

    public function testDecrementSpotsReturnsOkTrueOnSuccess(): void
    {
        $stmtLock = $this->createStub(PDOStatement::class);
        $stmtLock->method('fetch')->willReturn([
            'available_spots' => 10,
            'total_capacity' => 20,
            'is_blocked' => 0,
        ]);

        $stmtUpdate = $this->createStub(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtLock, $stmtUpdate);

        $result = new TimeSlot($pdo)->decrementSpots(1, 3);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testDecrementSpotsReturnsFailWhenSlotNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);

        $result = new TimeSlot($pdo)->decrementSpots(999);

        $this->assertFalse($result->ok);
        $this->assertSame('Slot no encontrado', $result->error);
    }

    public function testDecrementSpotsReturnsFailWhenBlocked(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'available_spots' => 10,
            'total_capacity' => 20,
            'is_blocked' => 1,
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);

        $result = new TimeSlot($pdo)->decrementSpots(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('bloqueado', $result->error);
    }

    public function testDecrementSpotsReturnsFailWhenNotEnoughSpots(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'available_spots' => 2,
            'total_capacity' => 20,
            'is_blocked' => 0,
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);

        $result = new TimeSlot($pdo)->decrementSpots(1, 5);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('plazas', $result->error);
    }

    public function testDecrementSpotsReturnsFailOnPdoException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('prepare')->willThrowException(new PDOException('Lock wait'));

        $result = new TimeSlot($pdo)->decrementSpots(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al decrementar', $result->error);
    }

    // ── incrementSpots ────────────────────────────────────────────

    public function testIncrementSpotsReturnsOkTrueOnSuccess(): void
    {
        $stmtLock = $this->createStub(PDOStatement::class);
        $stmtLock->method('fetch')->willReturn([
            'available_spots' => 5,
            'total_capacity' => 20,
        ]);

        $stmtUpdate = $this->createStub(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtLock, $stmtUpdate);

        $result = new TimeSlot($pdo)->incrementSpots(1, 2);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testIncrementSpotsReturnsFailWhenSlotNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);

        $result = new TimeSlot($pdo)->incrementSpots(999);

        $this->assertFalse($result->ok);
        $this->assertSame('Slot no encontrado', $result->error);
    }

    public function testIncrementSpotsCapsAtTotalCapacity(): void
    {
        $stmtLock = $this->createStub(PDOStatement::class);
        $stmtLock->method('fetch')->willReturn([
            'available_spots' => 18,
            'total_capacity' => 20,
        ]);

        $stmtUpdate = $this->createStub(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtLock, $stmtUpdate);

        // Adding 10 spots when only 2 are taken — should cap at 20
        $result = new TimeSlot($pdo)->incrementSpots(1, 10);

        $this->assertTrue($result->ok);
    }

    // ── blockSlot ─────────────────────────────────────────────────

    public function testBlockSlotReturnsOkTrue(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->blockSlot(1, 'Maintenance');

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testBlockSlotReturnsFailOnPdoException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('DB error'));

        $result = new TimeSlot($pdo)->blockSlot(1, 'Reason');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al bloquear', $result->error);
    }

    // ── unblockSlot ───────────────────────────────────────────────

    public function testUnblockSlotReturnsOkTrue(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->unblockSlot(1);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testUnblockSlotReturnsFailOnPdoException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('DB error'));

        $result = new TimeSlot($pdo)->unblockSlot(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al desbloquear', $result->error);
    }

    // ── create ────────────────────────────────────────────────────

    public function testCreateReturnsOkWithInsertId(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('7');

        $result = new TimeSlot($pdo)->create([
            'cafe_id' => 1,
            'slot_date' => '2024-07-01',
            'slot_time' => '10:00:00',
            'total_capacity' => 20,
        ]);

        $this->assertTrue($result->ok);
        $this->assertSame(7, $result->data);
    }

    public function testCreateUsesDefaultCapacity(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('3');

        $result = new TimeSlot($pdo)->create([
            'cafe_id' => 1,
            'slot_date' => '2024-07-01',
            'slot_time' => '11:00:00',
        ]);

        $this->assertTrue($result->ok);
    }

    public function testCreateReturnsFailOnPdoException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('Error'));

        $result = new TimeSlot($pdo)->create([
            'cafe_id' => 1,
            'slot_date' => '2024-07-01',
            'slot_time' => '10:00:00',
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al crear', $result->error);
    }

    // ── generateSlots ─────────────────────────────────────────────

    public function testGenerateSlotsReturnsCountOfCreatedSlots(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('1');

        // 1 day × 2 time slots = 2 created
        $result = new TimeSlot($pdo)->generateSlots(1, '2024-07-01', '2024-07-01', ['10:00', '14:00']);

        $this->assertTrue($result->ok);
        $this->assertSame(2, $result->data);
    }

    public function testGenerateSlotsReturnsFailOnException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('beginTransaction')->willThrowException(new PDOException('Error'));
        $pdo->method('inTransaction')->willReturn(false);

        $result = new TimeSlot($pdo)->generateSlots(1, '2024-07-01', '2024-07-01', ['10:00']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al generar', $result->error);
    }

    // ── getOccupancyStats ─────────────────────────────────────────

    public function testGetOccupancyStatsReturnsStats(): void
    {
        $stats = [
            'total_slots' => 10,
            'total_capacity_sum' => 200,
            'total_reserved' => 50,
            'avg_occupancy_percentage' => 25.0,
            'fully_booked_count' => 0,
            'blocked_count' => 1,
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($stats);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->getOccupancyStats(1, '2024-07-01', '2024-07-31');

        $this->assertTrue($result->ok);
        $this->assertSame(10, $result->data['total_slots']);
    }

    public function testGetOccupancyStatsReturnsEmptyArrayWhenNoData(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $result = new TimeSlot($this->stubPdoWithPrepare($stmt))->getOccupancyStats(1, '2024-07-01', '2024-07-31');

        $this->assertTrue($result->ok);
        $this->assertSame([], $result->data);
    }

    public function testGetOccupancyStatsReturnsFailOnPdoException(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('Error'));

        $result = new TimeSlot($pdo)->getOccupancyStats(1, '2024-07-01', '2024-07-31');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al obtener', $result->error);
    }
}
