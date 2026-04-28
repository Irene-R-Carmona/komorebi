<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AvailabilityService: validaciones de entrada para getAvailableSlots y assertSlotAvailable.
 * ¿Qué me quieres demostrar? Que IDs inválidos, formatos de fecha y hora incorrectos, cafés/pases inactivos,
 *   límites de pax y capacidad retornan Result::fail inmediatamente con el código correcto.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las guards de IDs, fecha, días-rango,
 *   café activo/reservable, pase activo/tipo, pax, duración o capacidad.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\AvailabilityService;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AvailabilityService::class)]
final class AvailabilityServiceTest extends ServiceTestCase
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

    // ──────────────────────────────────────────────
    // IDs y guests inválidos
    // ──────────────────────────────────────────────

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

    // ──────────────────────────────────────────────
    // Validación de formato y rango de fecha
    // ──────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWithInvalidDateFormat(): void
    {
        $result = $this->service->getAvailableSlots(1, 1, '01/01/2026', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_input', $result->code);
    }

    public function testGetAvailableSlotsFailsWithPastDate(): void
    {
        $result = $this->service->getAvailableSlots(1, 1, '2020-01-01', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('out_of_range', $result->code);
    }

    public function testGetAvailableSlotsFailsWithDateTooFarAhead(): void
    {
        $result = $this->service->getAvailableSlots(1, 1, '2099-12-31', 2);

        $this->assertFalse($result->ok);
        $this->assertSame('out_of_range', $result->code);
    }

    // ──────────────────────────────────────────────
    // Validaciones de café
    // ──────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenCafeNotFound(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_found', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenCafeIsInactive(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe(isActive: false));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_reservable', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenCafeHasNoReservations(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe(hasReservations: false));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 2);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_not_reservable', $result->code);
    }

    // ──────────────────────────────────────────────
    // Validaciones de pase
    // ──────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenPassNotFound(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe());
        $this->productRepoStub->method('findById')->willReturn(null);

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_found', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenPassIsInactive(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe());
        $this->productRepoStub->method('findById')->willReturn($this->makePass(isActive: false));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_available', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenPassHasWrongType(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe());
        $this->productRepoStub->method('findById')->willReturn($this->makePass(productType: 'food'));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_not_available', $result->code);
    }

    // ──────────────────────────────────────────────
    // Validaciones de pax
    // ──────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenGuestsBelowMinPax(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe());
        $this->productRepoStub->method('findById')->willReturn($this->makePass(minPax: 3));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 1);

        $this->assertFalse($result->ok);
        $this->assertSame('pax_not_allowed', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenGuestsExceedMaxPax(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe());
        $this->productRepoStub->method('findById')->willReturn($this->makePass(maxPax: 2));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 5);

        $this->assertFalse($result->ok);
        $this->assertSame('pax_not_allowed', $result->code);
    }

    // ──────────────────────────────────────────────
    // Duración y capacidad del café
    // ──────────────────────────────────────────────

    public function testGetAvailableSlotsFailsWhenPassDurationIsZero(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe());
        // target_cafe_types y target_animal_types null → passMatchesCafe devuelve true
        $this->productRepoStub->method('findById')->willReturn($this->makePass(duration: 0));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 2);

        $this->assertFalse($result->ok);
        $this->assertSame('pass_duration_invalid', $result->code);
    }

    public function testGetAvailableSlotsFailsWhenGuestsExceedCafeCapacity(): void
    {
        // capacityMax=2 < guests=10; maxPax=null para no fallar antes en pax_not_allowed
        $this->cafeRepoStub->method('findById')->willReturn($this->makeCafe(capacityMax: 2));
        $this->productRepoStub->method('findById')->willReturn($this->makePass(maxPax: null, duration: 60));

        $result = $this->service->getAvailableSlots(1, 1, $this->validFutureDate(), 10);

        $this->assertFalse($result->ok);
        $this->assertSame('capacity_exceeded', $result->code);
    }

    // ──────────────────────────────────────────────
    // assertSlotAvailable — formato de hora
    // ──────────────────────────────────────────────

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

    // ──────────────────────────────────────────────
    // Métodos de listado
    // ──────────────────────────────────────────────

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
