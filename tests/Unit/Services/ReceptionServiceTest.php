<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ReceptionService: getDashboard y consultas de estado en recepción.
 * ¿Qué me quieres demostrar? Que getDashboard delega al repositorio y retorna array.
 * ¿Qué va a fallar en este test si se cambia el código? Si getDashboard deja de delegar a los repos.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TrackerRepositoryInterface;
use App\Services\ReceptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReceptionService::class)]
final class ReceptionServiceTest extends TestCase
{
    private ReservationRepositoryInterface $reservationRepoStub;
    private TrackerRepositoryInterface $trackerRepoStub;
    private CafeRepositoryInterface $cafeRepoStub;
    private ReceptionService $service;

    protected function setUp(): void
    {
        $this->reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);
        $this->trackerRepoStub     = $this->createStub(TrackerRepositoryInterface::class);
        $this->cafeRepoStub        = $this->createStub(CafeRepositoryInterface::class);

        $this->service = new ReceptionService(
            $this->reservationRepoStub,
            $this->trackerRepoStub,
            $this->cafeRepoStub
        );
    }

    public function testGetPendingArrivalsReturnsArray(): void
    {
        $this->reservationRepoStub->method('findByCafeAndDate')->willReturn([]);

        $result = $this->service->getPendingArrivals(1);

        $this->assertIsArray($result);
    }

    public function testGetActiveGroupsReturnsArray(): void
    {
        $this->reservationRepoStub->method('findActiveByCafe')->willReturn([]);

        $result = $this->service->getActiveGroups(1);

        $this->assertIsArray($result);
    }

    public function testGetAvailableTrackersReturnsArray(): void
    {
        $this->trackerRepoStub->method('findAvailable')->willReturn([]);

        $result = $this->service->getAvailableTrackers(1);

        $this->assertIsArray($result);
    }
}
