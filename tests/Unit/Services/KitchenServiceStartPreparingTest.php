<?php

/**
 * ¿Qué pruebas aquí? KitchenService::startPreparing() — gestión del timestamp kitchen_started_at.
 * ¿Qué me quieres demostrar? Que updateKitchenStarted() sólo se llama cuando updateStatus() retorna true.
 * ¿Qué va a fallar en este test si se cambia el código? Si startPreparing() deja de llamar updateKitchenStarted
 *   cuando updateStatus retorna true, o si lo llama cuando retorna false.
 */

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\KitchenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KitchenService::class)]
final class KitchenServiceStartPreparingTest extends TestCase
{
    public function testStartPreparingCallsUpdateKitchenStartedWhenStatusUpdated(): void
    {
        $repo = $this->createMock(ReservationItemRepositoryInterface::class);
        $repo->method('updateStatus')->willReturn(true);
        $repo->expects($this->once())->method('updateKitchenStarted')->with(42);

        $service = new KitchenService($repo);
        $result = $service->startPreparing(42);

        $this->assertTrue($result);
    }

    public function testStartPreparingDoesNotCallUpdateKitchenStartedWhenStatusFails(): void
    {
        $repo = $this->createMock(ReservationItemRepositoryInterface::class);
        $repo->method('updateStatus')->willReturn(false);
        $repo->expects($this->never())->method('updateKitchenStarted');

        $service = new KitchenService($repo);
        $result = $service->startPreparing(99);

        $this->assertFalse($result);
    }
}
