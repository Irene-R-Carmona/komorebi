<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AdminStatisticsService: delegación a statsRepo y cálculo de tendencias.
 * ¿Qué me quieres demostrar? Que el servicio devuelve arrays con las claves esperadas y que los errores de PDO se manejan graciosamente.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las claves del array de estadísticas o se pierde el manejo de PDOException.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\AdminStatisticsService;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminStatisticsService::class)]
final class AdminStatisticsServiceTest extends TestCase
{
    private StatisticsRepositoryInterface $statsRepoStub;
    private AdminStatisticsService $service;

    protected function setUp(): void
    {
        $this->statsRepoStub = $this->createStub(StatisticsRepositoryInterface::class);
        $this->service       = new AdminStatisticsService($this->statsRepoStub);
    }

    public function testGetSystemStatisticsReturnsArray(): void
    {
        $this->statsRepoStub->method('getSystemCounts')->willReturn(['total_users' => 10, 'total_cafes' => 2]);
        $this->statsRepoStub->method('getWeeklyUserCounts')->willReturn(['current_week' => 5, 'previous_week' => 3]);
        $this->statsRepoStub->method('getWeeklyReservationCounts')->willReturn(['current_week' => 8, 'previous_week' => 6]);

        $result = $this->service->getSystemStatistics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_users', $result);
        $this->assertArrayHasKey('users_trend', $result);
        $this->assertArrayHasKey('reservations_trend', $result);
    }

    public function testGetSystemStatisticsHandlesPDOException(): void
    {
        $this->statsRepoStub->method('getSystemCounts')->willReturn(['total_users' => 5]);
        $this->statsRepoStub->method('getWeeklyUserCounts')->willThrowException(new PDOException('DB error'));

        $result = $this->service->getSystemStatistics();

        $this->assertIsArray($result);
        $this->assertSame('0%', $result['users_trend']);
        $this->assertSame('0%', $result['reservations_trend']);
    }

    public function testGetMonthlyStatsReturnsArray(): void
    {
        $this->statsRepoStub->method('getMonthlyStats')->willReturn([
            'reservations' => ['total_reservations' => 5, 'total_guests' => 10, 'unique_users' => 3, 'completed_reservations' => 4, 'cancelled_reservations' => 1, 'no_shows' => 0],
            'users'        => ['new_users' => 2],
            'reviews'      => ['total_reviews' => 3, 'avg_rating' => '4.2'],
        ]);

        $result = $this->service->getMonthlyStats(4, 2026);

        $this->assertIsArray($result);
    }

    public function testGetTopCafesReturnsArray(): void
    {
        $this->statsRepoStub->method('getTopCafes')->willReturn([['id' => 1, 'name' => 'Café A', 'avg_rating' => '4.5']]);

        $result = $this->service->getTopCafes('2026-01-01', '2026-12-31', 5);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetUserDistributionByRoleReturnsArray(): void
    {
        $this->statsRepoStub->method('getUserDistributionByRole')->willReturn([['role' => 'user', 'count' => 50]]);

        $result = $this->service->getUserDistributionByRole();

        $this->assertIsArray($result);
    }
}
