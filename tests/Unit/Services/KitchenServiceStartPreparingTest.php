<?php

/**
 * ¿Qué pruebas aquí? KitchenService::startPreparing() — UPDATE atómico de status + kitchen_started_at.
 * ¿Qué me quieres demostrar? Que startPreparing() delega en updateStatusAndKitchenStarted() y propaga su resultado.
 * ¿Qué va a fallar en este test si se cambia el código? Si startPreparing() deja de usar updateStatusAndKitchenStarted,
 *   o si no propaga correctamente true/false.
 */

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ReservationItem;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\KitchenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KitchenService::class)]
final class KitchenServiceStartPreparingTest extends TestCase
{
    public function testStartPreparingReturnsTrueWhenAtomicUpdateSucceeds(): void
    {
        $repo = $this->createMock(ReservationItemRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('updateStatusAndKitchenStarted')
            ->with(42, ReservationItem::STATUS_KITCHEN)
            ->willReturn(true);

        $service = new KitchenService($repo);
        $this->assertTrue($service->startPreparing(42));
    }

    public function testStartPreparingReturnsFalseWhenAtomicUpdateFails(): void
    {
        $repo = $this->createMock(ReservationItemRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('updateStatusAndKitchenStarted')
            ->with(99, ReservationItem::STATUS_KITCHEN)
            ->willReturn(false);

        $service = new KitchenService($repo);
        $this->assertFalse($service->startPreparing(99));
    }
}
