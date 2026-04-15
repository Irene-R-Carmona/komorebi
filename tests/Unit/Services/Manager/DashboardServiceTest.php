<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Services\Manager;

use App\Services\Manager\DashboardService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests para DashboardService
 *
 * Service layer que proporciona métricas en tiempo real para el dashboard del manager.
 */
final class DashboardServiceTest extends TestCase
{
    private DashboardService $service;

    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
    private PDO $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('PDO MySQL no disponible');
        }

        $this->db = $this->createStub(PDO::class);
        $this->service = new DashboardService($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->service, $this->db);
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DashboardService::class, $this->service);
    }

    public function testGetReservationsTodayReturnInteger(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['total' => 5]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getReservationsToday(1);

        $this->assertIsInt($result);
    }

    public function testGetRevenueTodayReturnFloat(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['revenue' => 125.50]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getRevenueToday(1);

        $this->assertIsFloat($result);
    }

    public function testGetActiveStaffCountReturnInteger(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['total' => 3]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getActiveStaffCount(1);

        $this->assertIsInt($result);
    }

    public function testGetAnimalsCountReturnInteger(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['total' => 8]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getAnimalsCount(1);

        $this->assertIsInt($result);
    }

    public function testGetDashboardMetricsReturnArray(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'total' => 10,
            'revenue' => 200.0,
        ]);
        $stmt->method('fetchAll')->willReturn([]);

        $this->db->method('prepare')->willReturn($stmt);

        $metrics = $this->service->getDashboardMetrics(1);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('reservations_today', $metrics);
        $this->assertArrayHasKey('revenue_today', $metrics);
        $this->assertArrayHasKey('active_staff', $metrics);
        $this->assertArrayHasKey('animals_count', $metrics);
    }

    public function testGetWeeklyRevenueReturnArray(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['date' => '2025-01-20', 'revenue' => 50.0],
            ['date' => '2025-01-21', 'revenue' => 75.0],
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getWeeklyRevenue(1);

        $this->assertIsArray($result);
    }

    public function testGetMonthlyReservationsCountReturnInteger(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['total' => 42]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getMonthlyReservationsCount(1);

        $this->assertIsInt($result);
    }

    public function testGetAverageRatingReturnFloat(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['rating_avg' => 4.7]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getAverageRating(1);

        $this->assertIsFloat($result);
    }

    public function testGetPendingReservationsCountReturnInteger(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['total' => 2]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getPendingReservationsCount(1);

        $this->assertIsInt($result);
    }

    public function testGetTopAnimalsReturnArray(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Luna', 'species' => 'Gato', 'interaction_count' => 15],
            ['id' => 2, 'name' => 'Mochi', 'species' => 'Gato', 'interaction_count' => 12],
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getTopAnimals(1, 5);

        $this->assertIsArray($result);
    }

    public function testGetReservationStatusDistributionReturnArray(): void
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['status' => 'confirmed', 'count' => 10],
            ['status' => 'completed', 'count' => 8],
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $result = $this->service->getReservationStatusDistribution(1);

        $this->assertIsArray($result);
    }
}
