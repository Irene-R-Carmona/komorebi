<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? TimeSlotService: delegación de getAvailableSlots al repositorio.
 * ¿Qué me quieres demostrar? Que getAvailableSlots delega al repositorio sin modificar la respuesta.
 * ¿Qué va a fallar en este test si se cambia el código? Si deja de delegarse o se filtra la respuesta.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Services\TimeSlotService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeSlotService::class)]
final class TimeSlotServiceTest extends TestCase
{
    public function testGetAvailableSlotsDelegatesToRepository(): void
    {
        $expected = [['id' => 1, 'time' => '10:00', 'available_spots' => 5]];

        $repoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $repoStub->method('findAvailableByDateFiltered')->willReturn($expected);

        $service = new TimeSlotService($repoStub);
        $result  = $service->getAvailableSlots('2025-12-01', 1, 2);

        $this->assertSame($expected, $result);
    }

    public function testGetAvailableSlotsReturnsEmptyArrayWhenNoneFound(): void
    {
        $repoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $repoStub->method('findAvailableByDateFiltered')->willReturn([]);

        $service = new TimeSlotService($repoStub);
        $result  = $service->getAvailableSlots('2025-12-01');

        $this->assertSame([], $result);
    }
}
