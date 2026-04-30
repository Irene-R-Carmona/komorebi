<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AdminStatisticsService: delegación a statsRepo, cálculo de tendencias y Result pattern.
 * ¿Qué me quieres demostrar? Que el servicio devuelve Result::ok con arrays correctos y maneja PDOException graciosamente.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las claves del array o se pierde el manejo de PDOException o el Result pattern.
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
        $this->service = new AdminStatisticsService($this->statsRepoStub);
    }

    public function testGetSystemStatisticsReturnsArray(): void
    {
        $this->statsRepoStub->method('getSystemCounts')->willReturn(['total_users' => 10, 'total_cafes' => 2]);
        $this->statsRepoStub->method('getWeeklyUserCounts')->willReturn(['current_week' => 5, 'previous_week' => 3]);
        $this->statsRepoStub->method('getWeeklyReservationCounts')->willReturn(['current_week' => 8, 'previous_week' => 6]);

        $result = $this->service->getSystemStatistics();

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertArrayHasKey('total_users', $result->data);
        $this->assertArrayHasKey('users_trend', $result->data);
        $this->assertArrayHasKey('reservations_trend', $result->data);
    }

    public function testGetSystemStatisticsHandlesPDOException(): void
    {
        $this->statsRepoStub->method('getSystemCounts')->willReturn(['total_users' => 5]);
        $this->statsRepoStub->method('getWeeklyUserCounts')->willThrowException(new PDOException('DB error'));

        $result = $this->service->getSystemStatistics();

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertSame('0%', $result->data['users_trend']);
        $this->assertSame('0%', $result->data['reservations_trend']);
    }

    public function testGetMonthlyStatsReturnsArray(): void
    {
        $this->statsRepoStub->method('getMonthlyStats')->willReturn([
            'reservations' => ['total_reservations' => 5, 'total_guests' => 10, 'unique_users' => 3, 'completed_reservations' => 4, 'cancelled_reservations' => 1, 'no_shows' => 0],
            'users' => ['new_users' => 2],
            'reviews' => ['total_reviews' => 3, 'avg_rating' => '4.2'],
        ]);

        $result = $this->service->getMonthlyStats(4, 2026);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
    }

    public function testGetTopCafesReturnsArray(): void
    {
        $this->statsRepoStub->method('getTopCafes')->willReturn([['id' => 1, 'name' => 'Café A', 'avg_rating' => '4.5']]);

        $result = $this->service->getTopCafes('2026-01-01', '2026-12-31', 5);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertCount(1, $result->data);
    }

    public function testGetUserDistributionByRoleReturnsArray(): void
    {
        $this->statsRepoStub->method('getUserDistributionByRole')->willReturn([['role' => 'user', 'count' => 50]]);

        $result = $this->service->getUserDistributionByRole();

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
    }

    public function testGetCafePerformanceStatsReturnsDelegatedData(): void
    {
        $expected = [['cafe_id' => 1, 'reservations' => 20]];
        $this->statsRepoStub->method('getCafePerformanceStats')->willReturn($expected);

        $result = $this->service->getCafePerformanceStats('2026-01-01', '2026-01-31');

        $this->assertTrue($result->ok);
        $this->assertSame($expected, $result->data);
    }

    public function testGetCafePerformanceStatsReturnsFail(): void
    {
        $this->statsRepoStub->method('getCafePerformanceStats')
            ->willThrowException(new PDOException('DB error'));

        $result = $this->service->getCafePerformanceStats('2026-01-01', '2026-01-31');

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetReservationTrendStatsReturnsDelegatedData(): void
    {
        $expected = [['date' => '2026-01-01', 'count' => 5]];
        $this->statsRepoStub->method('getReservationTrendStats')->willReturn($expected);

        $result = $this->service->getReservationTrendStats('2026-01-01', '2026-01-31');

        $this->assertTrue($result->ok);
        $this->assertSame($expected, $result->data);
    }

    public function testGetReservationTrendStatsReturnsFail(): void
    {
        $this->statsRepoStub->method('getReservationTrendStats')
            ->willThrowException(new PDOException('DB error'));

        $result = $this->service->getReservationTrendStats('2026-01-01', '2026-01-31');

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetReservationsByCafeTypeReturnsDelegatedData(): void
    {
        $expected = [['type' => 'animal_cafe', 'count' => 10]];
        $this->statsRepoStub->method('getReservationsByCafeType')->willReturn($expected);

        $result = $this->service->getReservationsByCafeType('2026-01-01', '2026-01-31');

        $this->assertTrue($result->ok);
        $this->assertSame($expected, $result->data);
    }

    public function testGetReservationsByCafeTypeReturnsFail(): void
    {
        $this->statsRepoStub->method('getReservationsByCafeType')
            ->willThrowException(new PDOException('DB error'));

        $result = $this->service->getReservationsByCafeType('2026-01-01', '2026-01-31');

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetMonthlyStatsReturnsFail(): void
    {
        $this->statsRepoStub->method('getMonthlyStats')
            ->willThrowException(new PDOException('DB error'));

        $result = $this->service->getMonthlyStats(1, 2026);

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetTopCafesReturnsFail(): void
    {
        $this->statsRepoStub->method('getTopCafes')
            ->willThrowException(new PDOException('DB error'));

        $result = $this->service->getTopCafes('2026-01-01', '2026-01-31');

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }

    public function testGetSystemStatisticsOuterPDOException(): void
    {
        $this->statsRepoStub->method('getSystemCounts')
            ->willThrowException(new PDOException('DB error'));

        $result = $this->service->getSystemStatistics();

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }
}
