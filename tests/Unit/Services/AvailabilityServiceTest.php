<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AvailabilityService: validaciones de entrada para getAvailableSlots y assertSlotAvailable.
 * ¿Qué me quieres demostrar? Que IDs inválidos y formatos de hora incorrectos retornan Result::fail inmediatamente.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las guards de cafeId<=0/passId<=0/guests<=0 o la validación del formato HH:MM.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\AvailabilityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AvailabilityService::class)]
final class AvailabilityServiceTest extends TestCase
{
    private CafeRepositoryInterface $cafeRepoStub;
    private ProductRepositoryInterface $productRepoStub;
    private ReservationRepositoryInterface $reservationRepoStub;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->cafeRepoStub        = $this->createStub(CafeRepositoryInterface::class);
        $this->productRepoStub     = $this->createStub(ProductRepositoryInterface::class);
        $this->reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->service             = new AvailabilityService(
            $this->cafeRepoStub,
            $this->productRepoStub,
            $this->reservationRepoStub
        );
    }

    public function testGetAvailableSlotsFailsWhenCafeIdIsZero(): void
    {
        $result = $this->service->getAvailableSlots(0, 1, '2026-06-01', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenPassIdIsZero(): void
    {
        $result = $this->service->getAvailableSlots(1, 0, '2026-06-01', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenGuestsIsZero(): void
    {
        $result = $this->service->getAvailableSlots(1, 1, '2026-06-01', 0);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenGuestsIsNegative(): void
    {
        $result = $this->service->getAvailableSlots(1, 1, '2026-06-01', -1);

        $this->assertFalse($result->ok);
    }

    public function testAssertSlotAvailableFailsWithInvalidTimeFormat(): void
    {
        $result = $this->service->assertSlotAvailable(1, 1, '2026-06-01', '900', 2);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Hora inválida', $result->error);
    }

    public function testAssertSlotAvailableFailsWithAlphaTime(): void
    {
        $result = $this->service->assertSlotAvailable(1, 1, '2026-06-01', 'ab:cd', 2);

        $this->assertFalse($result->ok);
    }

    public function testAssertSlotAvailableAcceptsValidTimeFormat(): void
    {
        // With stubs that return empty data, just verify no validation error on time format
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->assertSlotAvailable(1, 1, '2026-06-01', '10:00', 2);

        // Should not fail with 'invalid_input' time error
        $this->assertNotSame('invalid_input', $result->code);
    }

    public function testGetAvailableCafesForReservationReturnsArray(): void
    {
        $this->cafeRepoStub->method('findActive')->willReturn([]);

        $result = $this->service->getAvailableCafesForReservation();

        $this->assertIsArray($result);
    }

    public function testGetAvailablePassesForReservationReturnsArray(): void
    {
        $this->productRepoStub->method('findPasses')->willReturn([]);

        $result = $this->service->getAvailablePassesForReservation();

        $this->assertIsArray($result);
    }
}
