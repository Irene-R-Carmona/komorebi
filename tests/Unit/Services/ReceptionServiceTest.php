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
use App\Services\ReceptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(\App\Services\ReceptionService::class)]
final class ReceptionServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
    private PDO $pdoStub;

    // ─────────────────────────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdoStub = $this->createMock(\PDO::class);

        // Inyectamos el mismo stub en el singleton de Database para que
        // Database::transaction() también use nuestro PDO de prueba.
        $this->injectPdoIntoDatabase($this->pdoStub);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseSingleton();
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers de infraestructura
    // ─────────────────────────────────────────────────────────────

    /**
     * Reemplaza la conexión PDO del singleton Database::$instance
     * por el stub proporcionado, evitando toda conexión real a BD.
     */
    private function injectPdoIntoDatabase(\PDO $pdo): void
    {
        $reflection = new \ReflectionClass(Database::class);

        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setAccessible(true);

        $fakeInstance = $reflection->newInstanceWithoutConstructor();

        $connectionProp = $reflection->getProperty('connection');
        $connectionProp->setAccessible(true);
        $connectionProp->setValue($fakeInstance, $pdo);

        $instanceProp->setValue(null, $fakeInstance);
    }

    /**
     * Resetea el singleton de Database para no contaminar otros tests.
     */
    private function resetDatabaseSingleton(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, null);
    }

    /**
     * Crea un PDOStatement stub configurable.
     */
    private function makeStmt(
        mixed $fetchReturn = false,
        mixed $fetchAllReturn = [],
        mixed $fetchColumnReturn = 0
    ): \PDOStatement {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);

        return $stmt;
    }

    // ─────────────────────────────────────────────────────────────
    // Fixtures de datos
    // ─────────────────────────────────────────────────────────────

    private function confirmedReservation(): array
    {
        return [
            'id' => 42,
            'status' => Reservation::STATUS_CONFIRMED,
            'cafe_id' => 1,
            'user_id' => 10,
            'tracker_id' => null,
            'guests' => 2,
            'guest_count' => 2,
            'pass_unit_price' => '5.00',
            'check_in_at' => null,
            'check_out_at' => null,
        ];
    }

    /**
     * Reserva activa sin tracker ni user_id para evitar las ramas de
     * releaseTracker() y LoyaltyService en el checkout.
     */
    private function activeReservation(): array
    {
        return [
            'id' => 42,
            'status' => Reservation::STATUS_ACTIVE,
            'cafe_id' => 1,
            'user_id' => null,
            'tracker_id' => null,
            'guests' => 2,
            'guest_count' => 2,
            'pass_unit_price' => '5.00',
            'check_in_at' => '2024-01-01 14:00:00',
            'check_out_at' => null,
        ];
    }

    private function completedReservation(): array
    {
        return [
            'id' => 42,
            'status' => Reservation::STATUS_COMPLETED,
            'cafe_id' => 1,
            'user_id' => null,
            'tracker_id' => null,
            'final_price' => '10.00',
            'check_in_at' => '2024-01-01 14:00:00',
            'check_out_at' => '2024-01-01 16:00:00',
        ];
    }

    private function availableTracker(): array
    {
        return [
            'id' => 5,
            'cafe_id' => 1,
            'code' => 'A01',
            'status' => Tracker::STATUS_AVAILABLE,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // getPendingArrivals
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getPendingArrivals_returns_only_confirmed_reservations(): void
    {
        $stmt = $this->makeStmt(
            fetchAllReturn: [
                ['id' => 1, 'status' => Reservation::STATUS_CONFIRMED],
                ['id' => 2, 'status' => Reservation::STATUS_ACTIVE],
                ['id' => 3, 'status' => Reservation::STATUS_CONFIRMED],
                ['id' => 4, 'status' => Reservation::STATUS_PENDING],
                ['id' => 5, 'status' => Reservation::STATUS_CANCELLED],
            ]
        );
        $this->pdoStub->method('prepare')->willReturn($stmt);

        $service = new ReceptionService($this->pdoStub);
        $arrivals = $service->getPendingArrivals(1);

        $this->assertCount(2, $arrivals);
        foreach ($arrivals as $r) {
            $this->assertSame(Reservation::STATUS_CONFIRMED, $r['status']);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // processCheckin — camino feliz
    // ─────────────────────────────────────────────────────────────

    /**
     * processCheckin dispara 4 llamadas a prepare() en orden:
     *   1. ReceptionService: Reservation::findById()
     *   2. ReceptionService: Tracker::findById()
     *   3. Reservation::checkIn(): findById() interno
     *   4. Reservation::checkIn(): UPDATE reservations
     */
    #[Test]
    public function test_processCheckin_with_valid_reservation_and_tracker_returns_ok_result(): void
    {
        $confirmed = $this->confirmedReservation();
        $tracker = $this->availableTracker();

        $this->pdoStub->method('prepare')->willReturnOnConsecutiveCalls(
            $this->makeStmt($confirmed),    // findById (service)
            $this->makeStmt($tracker),      // tracker findById
            $this->makeStmt($confirmed),    // findById interno en checkIn()
            $this->makeStmt()               // UPDATE (execute devuelve true)
        );

        $service = new ReceptionService($this->pdoStub);
        $result = $service->processCheckin(42, 5);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // processCheckin — fallos
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_processCheckin_with_nonexistent_reservation_returns_failure(): void
    {
        // fetch() devuelve false → findById retorna null → Result::fail con code 'not_found'
        $this->pdoStub->method('prepare')->willReturn($this->makeStmt(false));

        $service = new ReceptionService($this->pdoStub);
        $result = $service->processCheckin(999, 5);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    #[Test]
    public function test_processCheckin_with_non_confirmed_reservation_returns_failure(): void
    {
        // La reserva ya está activa: estado inválido para check-in
        $this->pdoStub->method('prepare')->willReturn($this->makeStmt($this->activeReservation()));

        $service = new ReceptionService($this->pdoStub);
        $result = $service->processCheckin(42, 5);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    #[Test]
    public function test_processCheckin_with_unavailable_tracker_returns_failure(): void
    {
        $unavailableTracker = ['id' => 5, 'cafe_id' => 1, 'code' => 'A01', 'status' => Tracker::STATUS_IN_USE];

        $this->pdoStub->method('prepare')->willReturnOnConsecutiveCalls(
            $this->makeStmt($this->confirmedReservation()),  // findById reserva OK
            $this->makeStmt($unavailableTracker)             // tracker en uso → fail
        );

        $service = new ReceptionService($this->pdoStub);
        $result = $service->processCheckin(42, 5);

        $this->assertFalse($result->ok);
        $this->assertSame('tracker_not_available', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // processCheckout — camino feliz
    // ─────────────────────────────────────────────────────────────

    /**
     * processCheckout dispara 5 llamadas a prepare() en orden:
     *   1. ReceptionService: Reservation::findById()
     *   2. Reservation::checkOut(): findById() interno
     *   3. calculateFinalPrice(): SELECT SUM de reservation_items
     *   4. Reservation::checkOut(): UPDATE reservations
     *   5. ReceptionService: Reservation::findById() post-checkout
     */
    #[Test]
    public function test_processCheckout_with_active_reservation_returns_ok_result(): void
    {
        $active = $this->activeReservation();
        $completed = $this->completedReservation();

        $this->pdoStub->method('prepare')->willReturnOnConsecutiveCalls(
            $this->makeStmt($active),                  // findById (service)
            $this->makeStmt($active),                  // findById interno en checkOut()
            $this->makeStmt(fetchColumnReturn: 0),     // SUM items (calculateFinalPrice)
            $this->makeStmt(),                         // UPDATE (execute → true)
            $this->makeStmt($completed)                // findById post-checkout
        );

        $service = new ReceptionService($this->pdoStub);
        $result = $service->processCheckout(42);

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
        $this->pdoStub->method('prepare')->willReturn($this->makeStmt(false));

        $service = new ReceptionService($this->pdoStub);
        $result = $service->processCheckout(999);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    #[Test]
    public function test_processCheckout_without_prior_checkin_returns_failure(): void
    {
        // La reserva está en "confirmed", no en "active": aún no ha hecho check-in
        $this->pdoStub->method('prepare')->willReturn($this->makeStmt($this->confirmedReservation()));

        $service = new ReceptionService($this->pdoStub);
        $result = $service->processCheckout(42);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // getProtocolStatus
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function test_getProtocolStatus_returns_ok_with_protocol_data_when_reservation_exists(): void
    {
        $reservation = [
            'id' => 42,
            'status' => Reservation::STATUS_ACTIVE,
            'protocol_hygiene' => 1,
            'protocol_briefing' => 1,
            'protocol_shoes' => 1,
        ];
        $this->pdoStub->method('prepare')->willReturn($this->makeStmt($reservation));

        $service = new ReceptionService($this->pdoStub);
        $result = $service->getProtocolStatus(42);

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
        $this->pdoStub->method('prepare')->willReturn($this->makeStmt(false));

        $service = new ReceptionService($this->pdoStub);
        $result = $service->getProtocolStatus(999);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }
}
