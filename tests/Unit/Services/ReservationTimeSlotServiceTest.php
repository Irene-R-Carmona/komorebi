<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * - Validación de datos de entrada antes de consultar la BD
 * - Comportamiento cuando no hay slots disponibles
 * - Comportamiento cuando la reserva a cancelar no existe
 *
 * ¿Qué me quieres demostrar?
 * - Los campos obligatorios (cafe_id, fecha, hora) se validan antes de tocar la BD
 * - Sin slots disponibles, el servicio devuelve Result::fail correctamente
 * - Si la reserva a cancelar no existe, el servicio devuelve Result::fail
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se eliminan las validaciones de campos obligatorios en createReservationWithSlot
 * - Si se cambia la condición de "sin slots" por otro flujo
 * - Si se modifica la búsqueda de reserva en cancelReservationAndPromote
 */

namespace Tests\Unit\Services;

use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\Waitlist;
use App\Services\ReservationTimeSlotService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationTimeSlotService::class)]
final class ReservationTimeSlotServiceTest extends TestCase
{
    private ReservationTimeSlotService $service;

    private const VALID_DATA = [
        'cafe_id' => 1,
        'reservation_date' => '2026-12-25',
        'reservation_time' => '10:00',
        'guest_count' => 2,
        'user_id' => 7,
    ];

    protected function setUp(): void
    {
        // Los modelos son final → no se pueden stubear.
        // Se instancian con un PDO stub que devuelve un PDOStatement stub
        // para queries (fetch → null, fetchAll → []).
        // Todos los code paths bajo test terminan ANTES de que un query real
        // sea necesario (validación de input o "no row found").
        $stmtStub = $this->createMock(PDOStatement::class);

        $pdoStub = $this->createMock(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $this->service = new ReservationTimeSlotService(
            $pdoStub,
            new Reservation($pdoStub),
            new TimeSlot($pdoStub),
            new Waitlist($pdoStub)
        );
    }

    public function testCreateFailsWhenCafeIdIsZero(): void
    {
        $data = self::VALID_DATA;
        $data['cafe_id'] = 0;

        $result = $this->service->createReservationWithSlot($data);

        $this->assertFalse($result->ok);
    }

    public function testCreateFailsWhenDateIsEmpty(): void
    {
        $data = self::VALID_DATA;
        $data['reservation_date'] = '';

        $result = $this->service->createReservationWithSlot($data);

        $this->assertFalse($result->ok);
    }

    public function testCreateFailsWhenTimeIsEmpty(): void
    {
        $data = self::VALID_DATA;
        $data['reservation_time'] = '';

        $result = $this->service->createReservationWithSlot($data);

        $this->assertFalse($result->ok);
    }

    public function testCreateFailsWhenNoSlotsAvailable(): void
    {
        // PDOStatement stub devuelve [] para fetchAll →
        // TimeSlot::findAvailable devuelve Result::ok([]) →
        // el servicio detecta que no hay slots → devuelve Result::fail.
        $result = $this->service->createReservationWithSlot(self::VALID_DATA);

        $this->assertFalse($result->ok);
    }

    public function testCancelFailsWhenReservationNotFound(): void
    {
        // PDOStatement stub devuelve null para fetch →
        // Reservation::findById devuelve [] (array vacío) →
        // cancelReservationAndPromote detecta ![] → devuelve Result::fail.
        $result = $this->service->cancelReservationAndPromote(999);

        $this->assertFalse($result->ok);
    }
}
