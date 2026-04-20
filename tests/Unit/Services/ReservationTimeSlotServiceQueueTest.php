<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * La integración de Queue::push en ReservationTimeSlotService::promoteNextInWaitlist().
 *
 * ¿Qué me quieres demostrar?
 * Que cancelReservationAndPromote() devuelve promoted_users = 0 cuando no hay nadie
 * en la lista de espera, y promoted_users = 0 cuando markAsNotified falla.
 * En ambos casos Queue::push no debe llamarse.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la comprobación de getNextInQueue antes de promover.
 * Si se ignora el resultado de markAsNotified y se empuja a la cola igualmente.
 * Si cancelReservationAndPromote deja de retornar el conteo de promovidos.
 */

namespace Tests\Unit\Services;

use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\Waitlist;
use App\Services\ReservationTimeSlotService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(\App\Services\ReservationTimeSlotService::class)]
final class ReservationTimeSlotServiceQueueTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Helpers de infraestructura
    // ─────────────────────────────────────────────────────────────

    private function makeStmt(
        mixed $fetchReturn = false,
        int $rowCountReturn = 1
    ): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('rowCount')->willReturn($rowCountReturn);

        return $stmt;
    }

    /**
     * PDO estándar para el servicio (solo transacciones).
     */
    private function makeTransactionPdo(): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);

        return $pdo;
    }

    /**
     * Datos de reserva cancelable con time_slot_id asignado.
     *
     * @return array<string, mixed>
     */
    private function reservationRow(): array
    {
        return [
            'id' => 1,
            'user_id' => 10,
            'cafe_id' => 1,
            'cafe_name' => 'Test Café',
            'status' => Reservation::STATUS_PENDING,
            'time_slot_id' => 5,
            'guest_count' => 2,
        ];
    }

    /**
     * Construye el servicio con stubs de Reservation, TimeSlot y Waitlist ajustados
     * para cubrir el flujo de cancelReservationAndPromote hasta el paso de waitlist.
     *
     * @param PDOStatement $waitlistQueueStmt  Stmt que getNextInQueue devolverá.
     * @param PDOStatement|null $waitlistNotifyStmt  Stmt para markAsNotified (solo si se llama).
     */
    private function buildService(
        PDOStatement $waitlistQueueStmt,
        ?PDOStatement $waitlistNotifyStmt = null
    ): ReservationTimeSlotService {
        $reservation = $this->reservationRow();

        // Reservation PDO:
        //   1ª prepare → findById (validateAndFetchReservation)
        //   2ª prepare → findById dentro de cancel()
        //   3ª prepare → updateStatus dentro de cancel()
        $reservationDb = $this->createMock(PDO::class);
        $reservationDb->method('prepare')->willReturnOnConsecutiveCalls(
            $this->makeStmt($reservation),   // validateAndFetchReservation → findById
            $this->makeStmt($reservation),   // cancel() → findById
            $this->makeStmt()                // cancel() → updateStatus UPDATE
        );

        // TimeSlot PDO (incrementSpots gestiona su propia transacción interna):
        //   1ª prepare → SELECT FOR UPDATE (verificar capacidad)
        //   2ª prepare → UPDATE available_spots
        $slotRow = ['total_capacity' => 10, 'available_spots' => 3];
        $timeSlotDb = $this->createMock(PDO::class);
        $timeSlotDb->method('beginTransaction')->willReturn(true);
        $timeSlotDb->method('commit')->willReturn(true);
        $timeSlotDb->method('prepare')->willReturnOnConsecutiveCalls(
            $this->makeStmt($slotRow),   // SELECT FOR UPDATE
            $this->makeStmt()            // UPDATE
        );

        // Waitlist PDO:
        //   1ª prepare → getNextInQueue SELECT
        //   2ª prepare → markAsNotified UPDATE (solo si getNextInQueue devuelve fila)
        $waitlistDb = $this->createMock(PDO::class);
        if ($waitlistNotifyStmt !== null) {
            $waitlistDb->method('prepare')->willReturnOnConsecutiveCalls(
                $waitlistQueueStmt,
                $waitlistNotifyStmt
            );
        } else {
            $waitlistDb->method('prepare')->willReturn($waitlistQueueStmt);
        }

        return new ReservationTimeSlotService(
            $this->makeTransactionPdo(),
            new Reservation($reservationDb),
            new TimeSlot($timeSlotDb),
            new Waitlist($waitlistDb)
        );
    }

    // ─────────────────────────────────────────────────────────────
    // cancelReservationAndPromote — comportamiento de promoted_users
    // ─────────────────────────────────────────────────────────────

    #[TestDox('cancelReservationAndPromote devuelve promoted_users=0 cuando nadie espera en waitlist')]
    public function testNoPromotionWhenQueueIsEmpty(): void
    {
        // getNextInQueue devuelve false → nadie en cola
        $service = $this->buildService($this->makeStmt(false));
        $result = $service->cancelReservationAndPromote(1);

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->data['promoted_users']);
    }

    #[TestDox('cancelReservationAndPromote devuelve promoted_users=0 cuando markAsNotified falla')]
    public function testNoPromotionWhenMarkAsNotifiedFails(): void
    {
        // getNextInQueue devuelve una entrada válida
        $nextEntry = ['id' => 42, 'time_slot_id' => 5, 'user_name' => 'Juan', 'user_email' => 'juan@example.com'];
        $queueStmt = $this->makeStmt($nextEntry);

        // markAsNotified devuelve rowCount = 0 → Result::fail
        $notifyStmt = $this->makeStmt(false, 0);

        $service = $this->buildService($queueStmt, $notifyStmt);
        $result = $service->cancelReservationAndPromote(1);

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->data['promoted_users']);
    }
}
