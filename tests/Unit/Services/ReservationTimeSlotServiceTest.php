<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReservationTimeSlotService: createReservationWithSlot y cancelReservationAndPromote.
 * ¿Qué me quieres demostrar? Que cada fallo en la cadena de creación/cancelación retorna Result::fail
 *   correcto, y que el happy path retorna Result::ok con los campos esperados.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la validación de campos,
 *   la coordinación con TimeSlotRepository, o la lógica de promoción de waitlist.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\ReservationDTO;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Services\ReservationTimeSlotService;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationTimeSlotService::class)]
final class ReservationTimeSlotServiceTest extends TestCase
{
    private PDO $pdoStub;
    private ReservationRepositoryInterface $reservationStub;
    private TimeSlotRepositoryInterface $timeSlotStub;
    private WaitlistRepositoryInterface $waitlistStub;
    private ReservationTimeSlotService $service;

    protected function setUp(): void
    {
        $this->pdoStub = $this->createStub(PDO::class);
        $this->reservationStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->timeSlotStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $this->waitlistStub = $this->createStub(WaitlistRepositoryInterface::class);

        $this->service = new ReservationTimeSlotService(
            $this->pdoStub,
            $this->reservationStub,
            $this->timeSlotStub,
            $this->waitlistStub
        );
    }

    // ──────────────────────────────────────────────
    // createReservationWithSlot — validación de entrada
    // ──────────────────────────────────────────────

    public function testCreateReservationWithSlotFailsWhenCafeIdMissing(): void
    {
        $result = $this->service->createReservationWithSlot([
            'reservation_date' => '2025-12-01',
            'reservation_time' => '10:00',
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('incompletos', $result->error);
    }

    public function testCreateReservationWithSlotFailsWhenDateMissing(): void
    {
        $result = $this->service->createReservationWithSlot([
            'cafe_id' => 1,
            'reservation_time' => '10:00',
        ]);

        $this->assertFalse($result->ok);
    }

    public function testCreateReservationWithSlotFailsWhenTimeMissing(): void
    {
        $result = $this->service->createReservationWithSlot([
            'cafe_id' => 1,
            'reservation_date' => '2025-12-01',
        ]);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────
    // createReservationWithSlot — cadena de lógica
    // ──────────────────────────────────────────────

    public function testCreateFailsWhenNoSlotsAvailable(): void
    {
        $this->timeSlotStub->method('findAvailableByDateFiltered')->willReturn([]);

        $result = $this->service->createReservationWithSlot($this->validCreateData());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('slots', $result->error);
    }

    public function testCreateFailsWhenReserveSpotsFails(): void
    {
        $this->timeSlotStub->method('findAvailableByDateFiltered')->willReturn([['id' => 1]]);
        $this->timeSlotStub->method('reserveSpots')->willReturn(false);

        $result = $this->service->createReservationWithSlot($this->validCreateData());

        $this->assertFalse($result->ok);
    }

    public function testCreateFailsWhenReservationCreateFails(): void
    {
        $this->timeSlotStub->method('findAvailableByDateFiltered')->willReturn([['id' => 1]]);
        $this->timeSlotStub->method('reserveSpots')->willReturn(true);
        $this->reservationStub->method('create')->willReturn(0);

        $result = $this->service->createReservationWithSlot($this->validCreateData());

        $this->assertFalse($result->ok);
    }

    public function testCreateSucceedsHappyPath(): void
    {
        $this->timeSlotStub->method('findAvailableByDateFiltered')->willReturn([['id' => 42]]);
        $this->timeSlotStub->method('reserveSpots')->willReturn(true);
        $this->reservationStub->method('create')->willReturn(99);

        $result = $this->service->createReservationWithSlot($this->validCreateData());

        $this->assertTrue($result->ok);
        $this->assertSame(99, $result->data['reservation_id']);
        $this->assertSame(42, $result->data['time_slot_id']);
        $this->assertSame('confirmed', $result->data['status']);
    }

    // ──────────────────────────────────────────────
    // cancelReservationAndPromote
    // ──────────────────────────────────────────────

    public function testCancelFailsWhenReservationNotFound(): void
    {
        // findById returns null by default (stub)
        $result = $this->service->cancelReservationAndPromote(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Reserva no encontrada', $result->error);
    }

    public function testCancelFailsWhenUpdateStatusFails(): void
    {
        $this->reservationStub->method('findById')->willReturn($this->makeReservationDto());
        // updateStatus returns false by default (stub)

        $result = $this->service->cancelReservationAndPromote(1);

        $this->assertFalse($result->ok);
    }

    public function testCancelSucceedsWithNoTimeSlotId(): void
    {
        $this->reservationStub->method('findById')->willReturn($this->makeReservationDto(timeSlotId: null));
        $this->reservationStub->method('updateStatus')->willReturn(true);

        $result = $this->service->cancelReservationAndPromote(1);

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->data['promoted_users']);
    }

    public function testCancelSucceedsWithTimeSlotAndNoWaitlist(): void
    {
        $this->reservationStub->method('findById')->willReturn($this->makeReservationDto(timeSlotId: 5));
        $this->reservationStub->method('updateStatus')->willReturn(true);
        $this->timeSlotStub->method('releaseSpots')->willReturn(true);
        $this->waitlistStub->method('getNextInLine')->willReturn(null);

        $result = $this->service->cancelReservationAndPromote(1);

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->data['promoted_users']);
    }

    // ──────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validCreateData(): array
    {
        return [
            'cafe_id' => 1,
            'reservation_date' => '2026-06-01',
            'reservation_time' => '10:00',
            'guest_count' => 2,
            'user_id' => 1,
            'pass_product_id' => 1,
            'pass_name' => 'Pase básico',
            'pass_unit_price' => 1500,
            'pass_duration_minutes' => 60,
        ];
    }

    private function makeReservationDto(?int $timeSlotId = 5): ReservationDTO
    {
        return new ReservationDTO(
            id: 1,
            uuid: 'test-uuid',
            cafe_id: 1,
            user_id: 1,
            date: '2026-06-01',
            time: '10:00',
            guest_count: 2,
            status: 'confirmed',
            time_slot_id: $timeSlotId,
            pass_name: 'Pase básico',
            pass_duration_minutes: null,
            check_in_at: null,
            check_out_at: null,
            final_amount: null,
            payment_status: null,
            payment_method: null,
            notes: null,
        );
    }
}
