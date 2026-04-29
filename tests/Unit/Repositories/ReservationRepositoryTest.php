<?php

/**
 * ¿Qué prueba aquí? ReservationRepository — toda la lógica de acceso a datos de reservas.
 * ¿Qué me quieres demostrar? El repositorio construye las queries correctas y retorna los tipos esperados,
 *   incluyendo flujos multi-prepare (cancel, findByUser, getAvailableSlots, assignTracker).
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en la firma pública, PDO patterns o lógica
 *   de validación interna (cancel ownership, checkout payment method, state machine).
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\ReservationDTO;
use App\Repositories\ReservationRepository;
use InvalidArgumentException;
use PDO;

final class ReservationRepositoryTest extends RepositoryTestCase
{
    // ─────────────────────────────────────────────────────────────
    // findById
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsDtoWhenFound(): void
    {
        $row = RowFactory::reservationRow();
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new ReservationRepository($pdo);

        $dto = $repo->findById(1);

        $this->assertInstanceOf(ReservationDTO::class, $dto);
        $this->assertSame(1, $dto->id);
        $this->assertSame('confirmed', $dto->status);
    }

    public function testFindByIdReturnsNullWhenNoRow(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertNull($repo->findById(99));
    }

    // ─────────────────────────────────────────────────────────────
    // findActiveByUser
    // ─────────────────────────────────────────────────────────────

    public function testFindActiveByUserReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::reservationRow()]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findActiveByUser(1);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
    }

    public function testFindActiveByUserReturnsEmptyArrayWhenNone(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReservationRepository($pdo);

        $this->assertSame([], $repo->findActiveByUser(1));
    }

    // ─────────────────────────────────────────────────────────────
    // findByIdWithCafeDetails
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdWithCafeDetailsReturnsArrayWhenFound(): void
    {
        $row = RowFactory::reservationRow(['cafe_name' => 'Komorebi Madrid']);
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByIdWithCafeDetails(1);

        $this->assertIsArray($result);
        $this->assertSame('Komorebi Madrid', $result['cafe_name']);
    }

    public function testFindByIdWithCafeDetailsReturnsNullWhenNoRow(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertNull($repo->findByIdWithCafeDetails(99));
    }

    // ─────────────────────────────────────────────────────────────
    // findByCafeAndDate
    // ─────────────────────────────────────────────────────────────

    public function testFindByCafeAndDateReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [
            RowFactory::reservationRow(),
            RowFactory::reservationRow(['id' => 2]),
        ]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByCafeAndDate(1, '2025-06-15');

        $this->assertCount(2, $result);
    }

    public function testFindByCafeAndDateReturnsEmptyWhenNone(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReservationRepository($pdo);

        $this->assertSame([], $repo->findByCafeAndDate(1, '2025-06-15'));
    }

    // ─────────────────────────────────────────────────────────────
    // findByCafeWithFilters
    // ─────────────────────────────────────────────────────────────

    public function testFindByCafeWithFiltersReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::reservationRow()]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByCafeWithFilters(1, 'confirmed', '2025-06-15', 20);

        $this->assertCount(1, $result);
    }

    public function testFindByCafeWithFiltersAcceptsNullFilters(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReservationRepository($pdo);

        $this->assertSame([], $repo->findByCafeWithFilters(1, null, null, 20));
    }

    // ─────────────────────────────────────────────────────────────
    // updateStatus
    // ─────────────────────────────────────────────────────────────

    public function testUpdateStatusReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->updateStatus(1, 'confirmed'));
    }

    // ─────────────────────────────────────────────────────────────
    // checkIn
    // ─────────────────────────────────────────────────────────────

    public function testCheckInReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->checkIn(1, ['protocol_welcome' => true]));
    }

    // ─────────────────────────────────────────────────────────────
    // checkOut
    // ─────────────────────────────────────────────────────────────

    public function testCheckOutReturnsTrueWithoutPaymentData(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->checkOut(1));
    }

    public function testCheckOutReturnsTrueWithValidPaymentMethod(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReservationRepository($pdo);

        $result = $repo->checkOut(1, [
            'final_amount'   => 25,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $this->assertTrue($result);
    }

    public function testCheckOutThrowsOnInvalidPaymentMethod(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReservationRepository($pdo);

        $this->expectException(InvalidArgumentException::class);

        $repo->checkOut(1, ['payment_method' => 'bitcoin']);
    }

    // ─────────────────────────────────────────────────────────────
    // cancel
    // ─────────────────────────────────────────────────────────────

    public function testCancelReturnsFalseWhenReservationNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertFalse($repo->cancel(99, 1));
    }

    public function testCancelReturnsFalseWhenReservationBelongsToDifferentUser(): void
    {
        $row = RowFactory::reservationRow(['user_id' => 99, 'status' => 'confirmed']);
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => $row],
        ]);
        $repo = new ReservationRepository($pdo);

        $this->assertFalse($repo->cancel(1, 1));
    }

    public function testCancelReturnsFalseWhenStatusCannotTransitionToCancelled(): void
    {
        $row = RowFactory::reservationRow(['user_id' => 1, 'status' => 'active']);
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => $row],
        ]);
        $repo = new ReservationRepository($pdo);

        $this->assertFalse($repo->cancel(1, 1));
    }

    public function testCancelReturnsTrueWhenOwnerAndValidStatus(): void
    {
        $row = RowFactory::reservationRow(['user_id' => 1, 'status' => 'confirmed']);
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => $row],
            ['rowCount' => 1],
        ]);
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->cancel(1, 1));
    }

    public function testCancelReturnsTrueForPendingStatus(): void
    {
        $row = RowFactory::reservationRow(['user_id' => 2, 'status' => 'pending']);
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => $row],
            ['rowCount' => 1],
        ]);
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->cancel(1, 2));
    }

    // ─────────────────────────────────────────────────────────────
    // findByUser
    // ─────────────────────────────────────────────────────────────

    public function testFindByUserReturnsPaginatedData(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '5'],
            ['fetchAll'    => [RowFactory::reservationRow(), RowFactory::reservationRow(['id' => 2])]],
        ]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByUser(1, null, 20, 0);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(2, $result['data']);
        $this->assertSame(5, $result['total']);
    }

    public function testFindByUserWithStatusFilterReturnsFiltered(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '1'],
            ['fetchAll'    => [RowFactory::reservationRow(['status' => 'pending'])]],
        ]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByUser(1, 'pending', 10, 0);

        $this->assertSame(1, $result['total']);
        $this->assertSame('pending', $result['data'][0]['status']);
    }

    public function testFindByUserReturnsEmptyWhenNoReservations(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetchColumn' => '0'],
            ['fetchAll'    => []],
        ]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByUser(1);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['data']);
    }

    // ─────────────────────────────────────────────────────────────
    // findUpcomingByUser
    // ─────────────────────────────────────────────────────────────

    public function testFindUpcomingByUserReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::reservationRow()]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findUpcomingByUser(1, 3);

        $this->assertCount(1, $result);
    }

    public function testFindUpcomingByUserReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReservationRepository($pdo);

        $this->assertSame([], $repo->findUpcomingByUser(1));
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsReturnsEmptyWhenCafeNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertSame([], $repo->getAvailableSlots(99, '2025-06-15'));
    }

    public function testGetAvailableSlotsReturnsSlotsWhenNoBookings(): void
    {
        $cafeInfo = [
            'capacity_max' => 10,
            'opening_time' => '09:00:00',
            'closing_time' => '10:00:00',
        ];
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => $cafeInfo],
            ['fetch' => false],
        ]);
        $repo = new ReservationRepository($pdo);

        $slots = $repo->getAvailableSlots(1, '2025-06-15');

        $this->assertNotEmpty($slots);
        $this->assertSame('09:00', $slots[0]['time']);
        $this->assertSame(10, $slots[0]['available']);
        $this->assertTrue($slots[0]['bookable']);
    }

    // ─────────────────────────────────────────────────────────────
    // existsForUserAndDateTime
    // ─────────────────────────────────────────────────────────────

    public function testExistsForUserAndDateTimeReturnsTrueWhenExists(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['1' => 1]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->existsForUserAndDateTime(1, 1, '2025-06-15', '11:00:00');

        $this->assertTrue($result);
    }

    public function testExistsForUserAndDateTimeReturnsFalseWhenNone(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $result = $repo->existsForUserAndDateTime(1, 1, '2025-06-15', '11:00:00');

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────
    // findActiveByCafe
    // ─────────────────────────────────────────────────────────────

    public function testFindActiveByCafeReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::reservationRow(['status' => 'active'])]);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findActiveByCafe(1);

        $this->assertCount(1, $result);
        $this->assertSame('active', $result[0]['status']);
    }

    public function testFindActiveByCafeReturnsEmptyWhenNone(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new ReservationRepository($pdo);

        $this->assertSame([], $repo->findActiveByCafe(1));
    }

    // ─────────────────────────────────────────────────────────────
    // assignTracker
    // ─────────────────────────────────────────────────────────────

    public function testAssignTrackerReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['rowCount' => 1],
            ['rowCount' => 1],
        ]);
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->assignTracker(1, 5));
    }

    // ─────────────────────────────────────────────────────────────
    // completeProtocol
    // ─────────────────────────────────────────────────────────────

    public function testCompleteProtocolReturnsTrueOnSuccess(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->completeProtocol(1, 'welcome'));
    }

    // ─────────────────────────────────────────────────────────────
    // findByIdAndUser
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdAndUserReturnsArrayWhenFound(): void
    {
        $row = RowFactory::reservationRow(['cafe_name' => 'Komorebi Madrid', 'cafe_slug' => 'komorebi-madrid']);
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByIdAndUser(1, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cafe_name', $result);
    }

    public function testFindByIdAndUserReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertNull($repo->findByIdAndUser(99, 1));
    }

    // ─────────────────────────────────────────────────────────────
    // getDailyStats
    // ─────────────────────────────────────────────────────────────

    public function testGetDailyStatsReturnsStatsRow(): void
    {
        $row = [
            'total' => 10, 'completed' => 5, 'cancelled' => 1,
            'no_shows' => 0, 'current_guests' => 4, 'total_revenue' => '250.00',
        ];
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new ReservationRepository($pdo);

        $result = $repo->getDailyStats(1, '2025-06-15');

        $this->assertSame(10, $result['total']);
        $this->assertSame(5, $result['completed']);
    }

    public function testGetDailyStatsReturnsEmptyArrayWhenNoData(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertSame([], $repo->getDailyStats(1, '2025-06-15'));
    }

    // ─────────────────────────────────────────────────────────────
    // findByUuid
    // ─────────────────────────────────────────────────────────────

    public function testFindByUuidReturnsArrayWhenFound(): void
    {
        $row = RowFactory::reservationRow(['uuid' => 'test-uuid-0001']);
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new ReservationRepository($pdo);

        $result = $repo->findByUuid('test-uuid-0001');

        $this->assertIsArray($result);
        $this->assertSame('test-uuid-0001', $result['uuid']);
    }

    public function testFindByUuidReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertNull($repo->findByUuid('nonexistent'));
    }

    // ─────────────────────────────────────────────────────────────
    // isSlotAvailable
    // ─────────────────────────────────────────────────────────────

    public function testIsSlotAvailableReturnsTrueWhenNoBookings(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '0');
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->isSlotAvailable(1, '2025-06-15', '11:00:00'));
    }

    public function testIsSlotAvailableReturnsFalseWhenBooked(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '3');
        $repo = new ReservationRepository($pdo);

        $this->assertFalse($repo->isSlotAvailable(1, '2025-06-15', '11:00:00'));
    }

    // ─────────────────────────────────────────────────────────────
    // countByUser
    // ─────────────────────────────────────────────────────────────

    public function testCountByUserReturnsInt(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '7');
        $repo = new ReservationRepository($pdo);

        $this->assertSame(7, $repo->countByUser(1));
    }

    public function testCountByUserReturnsZeroWhenNone(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '0');
        $repo = new ReservationRepository($pdo);

        $this->assertSame(0, $repo->countByUser(1));
    }

    // ─────────────────────────────────────────────────────────────
    // hasCompletedReservation
    // ─────────────────────────────────────────────────────────────

    public function testHasCompletedReservationReturnsTrueWhenExists(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '2');
        $repo = new ReservationRepository($pdo);

        $this->assertTrue($repo->hasCompletedReservation(1, 1));
    }

    public function testHasCompletedReservationReturnsFalseWhenNone(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '0');
        $repo = new ReservationRepository($pdo);

        $this->assertFalse($repo->hasCompletedReservation(1, 1));
    }

    // ─────────────────────────────────────────────────────────────
    // findWithOperationalData
    // ─────────────────────────────────────────────────────────────

    public function testFindWithOperationalDataReturnsArrayWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::reservationRow());
        $repo = new ReservationRepository($pdo);

        $result = $repo->findWithOperationalData(1);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
    }

    public function testFindWithOperationalDataReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new ReservationRepository($pdo);

        $this->assertNull($repo->findWithOperationalData(99));
    }
}
