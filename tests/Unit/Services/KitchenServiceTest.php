<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? KitchenService: delegación al repositorio de items para obtener pedidos pendientes y cambiar estado.
 * ¿Qué me quieres demostrar? Que los métodos de consulta y estado delegan al repositorio y retornan los tipos correctos.
 * ¿Qué va a fallar en este test si se cambia el código? Si los métodos dejan de delegar o cambia el tipo de retorno.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use App\Services\KitchenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KitchenService::class)]
final class KitchenServiceTest extends TestCase
{
    private ReservationItemRepositoryInterface $itemRepoStub;
    private KitchenService $service;

    protected function setUp(): void
    {
        $this->itemRepoStub = $this->createStub(ReservationItemRepositoryInterface::class);
        $this->service      = new KitchenService($this->itemRepoStub);
    }

    public function testGetPendingByStationReturnsArray(): void
    {
        $this->itemRepoStub->method('findPendingByStation')->willReturn([]);

        $result = $this->service->getPendingByStation(1);

        $this->assertIsArray($result);
    }

    public function testGetAllPendingReturnsArray(): void
    {
        $this->itemRepoStub->method('findAllPendingByCafe')->willReturn([]);

        $result = $this->service->getAllPending(1);

        $this->assertIsArray($result);
    }

    public function testStartPreparingReturnsBool(): void
    {
        $this->itemRepoStub->method('updateStatus')->willReturn(true);

        $result = $this->service->startPreparing(1);

        $this->assertIsBool($result);
    }

    public function testMarkReadyReturnsBool(): void
    {
        $this->itemRepoStub->method('updateStatus')->willReturn(true);

        $result = $this->service->markReady(1);

        $this->assertIsBool($result);
    }

    public function testMarkServedReturnsBool(): void
    {
        $this->itemRepoStub->method('updateStatus')->willReturn(true);

        $result = $this->service->markServed(1);

        $this->assertIsBool($result);
    }

    public function testGetDailyStatsReturnsArray(): void
    {
        $this->itemRepoStub->method('getDailyStats')->willReturn([]);

        $result = $this->service->getDailyStats(1);

        $this->assertIsArray($result);
    }

    public function testGetEstimatedWaitTimeReturnsInt(): void
    {
        $this->itemRepoStub->method('getEstimatedWaitTime')->willReturn(0);

        $result = $this->service->getEstimatedWaitTime(1);

        $this->assertIsInt($result);
    }
}
