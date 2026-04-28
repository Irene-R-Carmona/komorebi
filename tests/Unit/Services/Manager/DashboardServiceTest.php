<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Manager\DashboardService: métricas del dashboard usando PDO inyectado.
 * ¿Qué me quieres demostrar? Que getDashboardMetrics retorna un array con las claves esperadas.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan o renombran las claves del array de métricas.
 */

namespace Tests\Unit\Services\Manager;

use App\Services\Manager\DashboardService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DashboardService::class)]
final class DashboardServiceTest extends TestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(['total' => 0, 'avg_rating' => 0.0, 'revenue' => 0.0]);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $this->service = new DashboardService($pdo);
    }

    public function testGetDashboardMetricsReturnsArrayWithExpectedKeys(): void
    {
        $metrics = $this->service->getDashboardMetrics(1);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('reservations_today', $metrics);
        $this->assertArrayHasKey('revenue_today', $metrics);
        $this->assertArrayHasKey('active_staff', $metrics);
        $this->assertArrayHasKey('animals_count', $metrics);
    }

    public function testGetReservationsTodayReturnsInteger(): void
    {
        $count = $this->service->getReservationsToday(1);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
