<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Servicio de recepción: check-in, check-out y filtrado de llegadas
 * pendientes para el dashboard de recepción.
 *
 * ¿Qué me quieres demostrar?
 * Que processCheckin devuelve Result::ok con reservas confirmadas y tracker
 * disponible, y Result::fail para reservas inexistentes, en estado
 * incorrecto o con tracker no disponible. Que processCheckout devuelve
 * Result::ok para reservas activas y Result::fail en caso contrario.
 * Y que getPendingArrivals filtra exclusivamente reservas en estado
 * "confirmed".
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de estado, si Result::ok/fail cambia de
 * semántica, si se cambia el filtro STATUS_CONFIRMED en getPendingArrivals,
 * o si el check de tracker disponible desaparece.
 */

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Models\Reservation;
use App\Models\Tracker;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TrackerRepositoryInterface;
use App\Services\ReceptionService;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(ReceptionService::class)]
final class ReceptionServiceTest extends TestCase
{
    /** @var Stub&ReservationRepositoryInterface */
    private ReservationRepositoryInterface $reservationRepo;
    /** @var Stub&TrackerRepositoryInterface */
    private TrackerRepositoryInterface $trackerRepo;
    private ReceptionService $service;

    // ─────────────────────────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->reservationRepo = $this->createMock(ReservationRepositoryInterface::class);
        $this->trackerRepo     = $this->createMock(TrackerRepositoryInterface::class);
        $this->service         = new ReceptionService($this->reservationRepo, $this->trackerRepo);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseSingleton();
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers de infraestructura
    // ─────────────────────────────────────────────────────────────

    private function injectPdoIntoDatabase(PDO $pdo): void
    {
        $reflection   = new ReflectionClass(Database::class);
        $instanceProp = $reflection->getProperty('instance');
        $fakeInstance = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('connection')->setValue($fakeInstance, $pdo);
        $instanceProp->setValue(null, $fakeInstance);
    }

    private function resetDatabaseSingleton(): void
    {
        $reflection = new ReflectionClass(Database::class);
        $reflection->getProperty('instance')->setValue(null, null);
    }

    private function makeTransactionPdo(): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $this->injectPdoIntoDatabase($pdo);

        return $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // Fixtures
    // ─────────────────────────────────────────────────────────────

    private function confirmedReservation(): array
    {
        return [
            'id'             => 42,
            'status'         => Reservation::STATUS_CONFIRMED,
            'cafe_id'        => 1,
            'user_id'        => 10,
            'tracker_id'     => null,
            'guests'         => 2,
            'guest_count'    => 2,
            'pass_unit_price'=> '5.00',
            'check_in_at'    => null,
            'check_out_at'   => null,
        ];
    }

    /**
     * Reserva activa sin user_id para evitar la rama de LoyaltyService en checkout.
     */
    private function activeReservation(): array
    {
        return [
            'id'             => 42,
            'status'         => Reservation::STATUS_ACTIVE,
            'cafe_id'        => 1,
            'user_id'        => null,
            'tracker_id'     => null,
            'guests'         => 2,
            'guest_count'    => 2,
            'pass_unit_price'=> '5.00',
            'check_in_at'    => '2024-01-01 14:00:00',
            'check_out_at'   => null,
        ];
    }

    private function completedReservation(): array
    {
        return [
            'id'           => 42,
            'status'       => Reservation::STATUS_COMPLETED,
            'cafe_id'      => 1,
            'user_id'      => null,
            'tracker_id'   => null,
            'final_amount' => '10.00',
            'check_in_at'  => '2024-01-01 14:00:00',
            'check_out_at' => '2024-01-01 16:00:00',
        ];
    }

    private function availableTracker(): array
    {
        return [
            'id'      => 5,
            'cafe_id' => 1,
            'code'    => 'A01',
            'status'  => Tracker::STATUS_AVAILABLE,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // getPendingArrivals
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getPendingArrivals_returns_only_confirmed_reservations(): void
    {
        $this->reservationRepo->method('findByCafeAndDate')->willReturn([
            ['id' => 1, 'status' => Reservation::STATUS_CONFIRMED],
            ['id' => 2, 'status' => Reservation::STATUS_ACTIVE],
            ['id' => 3, 'status' => Reservation::STATUS_CONFIRMED],
            ['id' => 4, 'status' => Reservation::STATUS_PENDING],
            ['id' => 5, 'status' => Reservation::STATUS_CANCELLED],
        ]);

        $arrivals = $this->service->getPendingArrivals(1);

        $this->assertCount(2, $arrivals);
        foreach ($arrivals as $r) {
            $this->assertSame(Reservation::STATUS_CONFIRMED, $r['status']);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // processCheckin — camino feliz
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_processCheckin_with_valid_reservation_and_tracker_returns_ok_result(): void
    {
        $this->makeTransactionPdo();

        $this->reservationRepo->method('findById')->willReturn($this->confirmedReservation());
        $this->trackerRepo->method('findById')->willReturn($this->availableTracker());
        $this->reservationRepo->method('checkIn')->willReturn(true);

        $result = $this->service->processCheckin(42, 5);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // processCheckin — fallos
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_processCheckin_with_nonexistent_reservation_returns_failure(): void
    {
        $this->makeTransactionPdo();
        $this->reservationRepo->method('findById')->willReturn(null);

        $result = $this->service->processCheckin(999, 5);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    #[Test]
    public function test_processCheckin_with_non_confirmed_reservation_returns_failure(): void
    {
        $this->makeTransactionPdo();
        $this->reservationRepo->method('findById')->willReturn($this->activeReservation());

        $result = $this->service->processCheckin(42, 5);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[Test]
    public function test_processCheckin_with_unavailable_tracker_returns_failure(): void
    {
        $this->makeTransactionPdo();
        $this->reservationRepo->method('findById')->willReturn($this->confirmedReservation());
        $this->trackerRepo->method('findById')->willReturn(
            ['id' => 5, 'cafe_id' => 1, 'code' => 'A01', 'status' => Tracker::STATUS_IN_USE]
        );

        $result = $this->service->processCheckin(42, 5);

        $this->assertFalse($result->ok);
        $this->assertSame('tracker_not_available', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // processCheckout — camino feliz
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_processCheckout_with_active_reservation_returns_ok_result(): void
    {
        $this->makeTransactionPdo();

        $this->reservationRepo->method('findById')->willReturnOnConsecutiveCalls(
            $this->activeReservation(),
            $this->completedReservation()
        );
        $this->reservationRepo->method('checkOut')->willReturn(true);

        $result = $this->service->processCheckout(42);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['success']);
        $this->assertArrayHasKey('final_price', $result->data);
        $this->assertArrayHasKey('duration', $result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // processCheckout — fallos
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_processCheckout_with_nonexistent_reservation_returns_failure(): void
    {
        $this->makeTransactionPdo();
        $this->reservationRepo->method('findById')->willReturn(null);

        $result = $this->service->processCheckout(999);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    #[Test]
    public function test_processCheckout_without_prior_checkin_returns_failure(): void
    {
        $this->makeTransactionPdo();
        $this->reservationRepo->method('findById')->willReturn($this->confirmedReservation());

        $result = $this->service->processCheckout(42);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // getProtocolStatus
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getProtocolStatus_returns_ok_with_protocol_data_when_reservation_exists(): void
    {
        $this->reservationRepo->method('findById')->willReturn([
            'id'                => 42,
            'status'            => Reservation::STATUS_ACTIVE,
            'protocol_hygiene'  => 1,
            'protocol_briefing' => 1,
            'protocol_shoes'    => 1,
        ]);

        $result = $this->service->getProtocolStatus(42);

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('hygiene', $result->data);
        $this->assertArrayHasKey('briefing', $result->data);
        $this->assertArrayHasKey('shoes', $result->data);
        $this->assertArrayHasKey('all_complete', $result->data);
        $this->assertTrue($result->data['all_complete']);
    }

    #[Test]
    public function test_getProtocolStatus_returns_failure_when_reservation_not_found(): void
    {
        $this->reservationRepo->method('findById')->willReturn(null);

        $result = $this->service->getProtocolStatus(999);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }
}
