<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de AvailabilityService: validaciones de entrada, reglas de negocio y cálculo de slots.
 *
 * ¿Qué me quieres demostrar?
 * Que las validaciones de entrada se aplican antes de tocar la BD, que los
 * errores de datos (café inexistente, pase inactivo, pax fuera de rango)
 * devuelven Result::fail con el código correcto, y que el camino feliz
 * calcula exactamente los slots esperados según horarios y reservas.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se elimina alguna validación de entrada (cafeId/passId/guests ≤ 0, formato fecha).
 * - Si cambia la verificación de is_active / has_reservations del café.
 * - Si se altera la lógica de min_pax / max_pax o compatibilidad pase-café.
 * - Si el cálculo de slots cambia (opening/closing/step/duration/solapamiento).
 * - Si assertSlotAvailable deja de derivar en getAvailableSlots o cambia su formato.
 */

namespace Tests\Unit\Services;

use App\Services\AvailabilityService;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AvailabilityServiceTest extends TestCase
{
    /** Fecha futura bien dentro de maxDaysAhead=999 para tests normales. */
    private const FUTURE_DATE = '2027-06-15';

    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
    private PDO $mockPdo;
    /** @var \PHPUnit\Framework\MockObject\Stub&\PDOStatement */
    private PDOStatement $mockStmt;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->mockStmt = $this->createStub(\PDOStatement::class);
        $this->mockStmt->method('execute')->willReturn(true);

        $this->mockPdo = $this->createStub(\PDO::class);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);

        // maxDaysAhead=999 para que los tests no fallen por rango de fechas
        $this->service = new AvailabilityService($this->mockPdo, 999, 30);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots — validaciones de entrada (sin BD)
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWithZeroCafeId(): void
    {
        $result = $this->service->getAvailableSlots(0, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWithNegativeCafeId(): void
    {
        $result = $this->service->getAvailableSlots(-5, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWithZeroPassId(): void
    {
        $result = $this->service->getAvailableSlots(1, 0, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWithZeroGuests(): void
    {
        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 0);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWithBadDateFormat(): void
    {
        $result = $this->service->getAvailableSlots(1, 1, '15/06/2027', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenDateIsPast(): void
    {
        // daysAhead < 0 → out_of_range
        $service = new AvailabilityService($this->mockPdo, 30, 30);

        $result = $service->getAvailableSlots(1, 1, '2025-01-01', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('out_of_range', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenDateExceedsMaxDaysAhead(): void
    {
        // maxDaysAhead=5, target date is years ahead
        $service = new AvailabilityService($this->mockPdo, 5, 30);

        $result = $service->getAvailableSlots(1, 1, '2028-12-01', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('out_of_range', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots — validaciones de café
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenCafeNotFound(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);

        $result = $this->service->getAvailableSlots(99, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_found', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenCafeIsInactive(): void
    {
        $this->mockStmt->method('fetch')->willReturn(
            $this->buildCafeRow(['is_active' => 0])
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_reservable', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenCafeDoesNotAcceptReservations(): void
    {
        $this->mockStmt->method('fetch')->willReturn(
            $this->buildCafeRow(['has_reservations' => 0])
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_reservable', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots — validaciones de pase
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenPassNotFound(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            false  // pass not found
        );

        $result = $this->service->getAvailableSlots(1, 99, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_found', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenPassIsInactive(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow(['is_active' => 0])
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_available', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenPassIsNotPassType(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow(['product_type' => 'merchandise'])
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_available', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots — reglas de pax
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenGuestsBelowMinPax(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow(['min_pax' => 3])  // requiere mínimo 3
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('pax_not_allowed', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenGuestsExceedMaxPax(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow(['max_pax' => 2])  // máximo 2
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 5);

        $this->assertFalse($result->ok);
        $this->assertSame('pax_not_allowed', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots — compatibilidad pase/café
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenPassNotCompatibleWithCafeCategory(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(['category' => 'cat']),
            $this->buildPassRow(['target_cafe_types' => '["dog"]'])
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_allowed', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenPassNotCompatibleWithAnimalType(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(['animal_type' => 'cat']),
            $this->buildPassRow(['target_animal_types' => '["rabbit"]'])
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_allowed', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots — capacidad y duración
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenPassDurationIsZero(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow(['duration_minutes' => 0])
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_duration_invalid', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenCafeCapacityIsZero(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(['capacity_max' => 0]),
            $this->buildPassRow()
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_capacity_invalid', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenGuestsExceedCafeCapacity(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(['capacity_max' => 3]),
            $this->buildPassRow()
        );

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 5);

        $this->assertFalse($result->ok);
        $this->assertSame('capacity_exceeded', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableSlots — camino feliz
    // ─────────────────────────────────────────────────────────────

    public function testGetAvailableSlotsReturnsSlotsHappyPath(): void
    {
        // Café 09:00–18:00, pase 60 min, step 30 min, sin reservas
        // → 17 slots: 09:00, 09:30, …, 17:00
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow()
        );
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data['slots']);
        $this->assertCount(17, $result->data['slots']);
        $this->assertSame('09:00', $result->data['slots'][0]);
        $this->assertSame('17:00', $result->data['slots'][16]);
        $this->assertSame(1, $result->data['cafe_id']);
        $this->assertSame(1, $result->data['pass_product_id']);
        $this->assertSame(self::FUTURE_DATE, $result->data['date']);
        $this->assertSame(2, $result->data['guests']);
    }

    public function testGetAvailableSlotsReturnsEmptyWhenCafeFullyBooked(): void
    {
        // capacity_max=2, reserva que ocupa todo el día, guests=2 → sin slots
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(['capacity_max' => 2]),
            $this->buildPassRow(['duration_minutes' => 60])
        );
        $this->mockStmt->method('fetchAll')->willReturn([
            [
                'reservation_time'     => '09:00:00',
                'pass_duration_minutes' => 540,  // 9h: cubre 09:00–18:00
                'guests'               => 2,
            ],
        ]);

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertTrue($result->ok);
        $this->assertEmpty($result->data['slots']);
    }

    public function testGetAvailableSlotsSkipsSlotsOccupiedByOverlappingReservation(): void
    {
        // Una reserva ocupa 10:00–11:00; los slots que solapen deben eliminarse
        // capacity_max=2, guests=2, reserva de 2 pax → ningún slot solapado disponible
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(['capacity_max' => 2]),
            $this->buildPassRow(['duration_minutes' => 60])
        );
        $this->mockStmt->method('fetchAll')->willReturn([
            [
                'reservation_time'     => '10:00:00',
                'pass_duration_minutes' => 60,
                'guests'               => 2,
            ],
        ]);

        $result = $this->service->getAvailableSlots(1, 1, self::FUTURE_DATE, 2);

        $this->assertTrue($result->ok);
        $slots = $result->data['slots'];
        // Los slots que solapen: 09:00(09:00–10:00), 09:30(09:30–10:30), 10:00(10:00–11:00)
        // → eliminados; el resto permanece
        $this->assertNotContains('09:30', $slots);
        $this->assertNotContains('10:00', $slots);
        // El slot 09:00 (fin=10:00, no hay solape con inicio=10:00 ya que resStart ≮ slotEnd=10:00)
        // resStart=600 < slotEnd=600? NO → no solapa. 09:00 SÍ disponible.
        $this->assertContains('09:00', $slots);
        // 11:00 (10:60+60=720) → resStart=600 < slotEnd=720 AND resEnd=660 > 660? NO → no solapa
        $this->assertContains('11:00', $slots);
    }

    // ─────────────────────────────────────────────────────────────
    // assertSlotAvailable
    // ─────────────────────────────────────────────────────────────

    public function testAssertSlotAvailableFailsWithInvalidTimeFormat(): void
    {
        $result = $this->service->assertSlotAvailable(1, 1, self::FUTURE_DATE, '10:00:00', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testAssertSlotAvailableFailsWhenTimeNotInAvailableSlots(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow()
        );
        $this->mockStmt->method('fetchAll')->willReturn([]);

        // '03:00' está antes de la apertura (09:00), nunca estará en los slots
        $result = $this->service->assertSlotAvailable(1, 1, self::FUTURE_DATE, '03:00', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('no_availability', $result->code);
    }

    public function testAssertSlotAvailableSucceedsForAvailableSlot(): void
    {
        $this->mockStmt->method('fetch')->willReturnOnConsecutiveCalls(
            $this->buildCafeRow(),
            $this->buildPassRow()
        );
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $result = $this->service->assertSlotAvailable(1, 1, self::FUTURE_DATE, '09:00', 2);

        $this->assertTrue($result->ok);
    }

    public function testAssertSlotAvailableFailsWhenUnderlyingGetSlotsFails(): void
    {
        // getAvailableSlots fallará porque el café no existe
        $this->mockStmt->method('fetch')->willReturn(false);

        $result = $this->service->assertSlotAvailable(99, 1, self::FUTURE_DATE, '09:00', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_found', $result->code);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function buildCafeRow(array $overrides = []): array
    {
        return array_merge([
            'id'               => 1,
            'category'         => 'cat',
            'animal_type'      => 'cat',
            'opening_time'     => '09:00:00',
            'closing_time'     => '18:00:00',
            'capacity_max'     => 10,
            'is_active'        => 1,
            'has_reservations' => 1,
        ], $overrides);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function buildPassRow(array $overrides = []): array
    {
        return array_merge([
            'id'                  => 1,
            'product_type'        => 'pass',
            'is_active'           => 1,
            'duration_minutes'    => 60,
            'min_pax'             => 1,
            'max_pax'             => 10,
            'target_cafe_types'   => null,
            'target_animal_types' => null,
            'attributes'          => '',
        ], $overrides);
    }
}
