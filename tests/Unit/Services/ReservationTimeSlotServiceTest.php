<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReservationTimeSlotService: validación de datos de entrada en createReservationWithSlot.
 * ¿Qué me quieres demostrar? Que createReservationWithSlot retorna fail si faltan campos obligatorios.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la validación de datos incompletos.
 */

namespace Tests\Unit\Services;

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
        $this->pdoStub        = $this->createStub(PDO::class);
        $this->reservationStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->timeSlotStub   = $this->createStub(TimeSlotRepositoryInterface::class);
        $this->waitlistStub   = $this->createStub(WaitlistRepositoryInterface::class);

        $this->service = new ReservationTimeSlotService(
            $this->pdoStub,
            $this->reservationStub,
            $this->timeSlotStub,
            $this->waitlistStub
        );
    }

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
            'cafe_id'          => 1,
            'reservation_time' => '10:00',
        ]);

        $this->assertFalse($result->ok);
    }

    public function testCreateReservationWithSlotFailsWhenTimeMissing(): void
    {
        $result = $this->service->createReservationWithSlot([
            'cafe_id'          => 1,
            'reservation_date' => '2025-12-01',
        ]);

        $this->assertFalse($result->ok);
    }
}
