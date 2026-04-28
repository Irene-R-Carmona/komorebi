<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? StaffShiftService: validación de horarios y delegación de consultas de turnos.
 * ¿Qué me quieres demostrar? Que assignShift retorna fail si la hora inicio >= fin o si hay solapamiento.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la validación de cruce de medianoche.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use App\Services\StaffShiftService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StaffShiftService::class)]
final class StaffShiftServiceTest extends TestCase
{
    private StaffShiftRepositoryInterface $repoStub;
    private StaffShiftService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(StaffShiftRepositoryInterface::class);
        $this->service  = new StaffShiftService($this->repoStub);
    }

    public function testAssignShiftFailsWhenStartIsAfterEnd(): void
    {
        $result = $this->service->assignShift(1, 1, '2025-12-01', '18:00', '09:00', null, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_shift_hours', $result->code);
    }

    public function testAssignShiftFailsWhenStartEqualsEnd(): void
    {
        $result = $this->service->assignShift(1, 1, '2025-12-01', '09:00', '09:00', null, 1);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_shift_hours', $result->code);
    }

    public function testAssignShiftFailsWhenOverlapExists(): void
    {
        $this->repoStub->method('hasOverlap')->willReturn(true);

        $result = $this->service->assignShift(1, 1, '2025-12-01', '09:00', '17:00', null, 1);

        $this->assertFalse($result->ok);
    }

    public function testAssignShiftSucceedsWhenNoOverlap(): void
    {
        $this->repoStub->method('hasOverlap')->willReturn(false);
        $this->repoStub->method('create')->willReturn(10);

        $result = $this->service->assignShift(1, 1, '2025-12-01', '09:00', '17:00', null, 1);

        $this->assertTrue($result->ok);
    }

    public function testGetWeekShiftsDelegatesToRepository(): void
    {
        $this->repoStub->method('findByCafeAndDateRange')->willReturn([]);

        $result = $this->service->getWeekShifts(1);

        $this->assertTrue($result->ok);
    }
}
