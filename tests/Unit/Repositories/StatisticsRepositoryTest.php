<?php

/**
 * ¿Qué prueba aquí? El repositorio StatisticsRepository: conteos de sistema, estadísticas semanales/mensuales,
 *   rendimiento de cafeterías, tendencias, distribución de roles, actividad reciente y datos para gráficos.
 * ¿Qué me quieres demostrar? Que cada método devuelve la estructura de array esperada a partir
 *   de los resultados del PDO stubado.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambian las claves devueltas,
 *   la lógica de avg_guests_per_reservation/zero-division, o la construcción de getRecentActivity.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\StatisticsRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatisticsRepository::class)]
final class StatisticsRepositoryTest extends TestCase
{
    /** Stub PDO cuyos query() y prepare() devuelven siempre el mismo stmt. */
    private function makePdo(
        array $fetchAllReturn = [],
        mixed $fetchReturn = false,
        mixed $fetchColumnReturn = '0'
    ): PDO {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);

        return $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // getSystemCounts
    // ─────────────────────────────────────────────────────────────

    public function testGetSystemCountsReturnsAllKeys(): void
    {
        $pdo = $this->makePdo([], [
            'users' => 5,
            'cafes' => 3,
            'reservations' => 10,
            'reviews' => 7,
            'pending_reviews' => 2,
        ]);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getSystemCounts();

        foreach (['users', 'cafes', 'reservations', 'reviews', 'pending_reviews'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
        $this->assertSame(5, $result['users']);
    }

    // ─────────────────────────────────────────────────────────────
    // getWeeklyUserCounts
    // ─────────────────────────────────────────────────────────────

    public function testGetWeeklyUserCountsReturnsExpectedKeys(): void
    {
        $pdo = $this->makePdo([], false, '3');
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getWeeklyUserCounts();

        $this->assertArrayHasKey('current_week', $result);
        $this->assertArrayHasKey('previous_week', $result);
        $this->assertSame(3, $result['current_week']);
    }

    // ─────────────────────────────────────────────────────────────
    // getWeeklyReservationCounts
    // ─────────────────────────────────────────────────────────────

    public function testGetWeeklyReservationCountsReturnsExpectedKeys(): void
    {
        $pdo = $this->makePdo([], false, '7');
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getWeeklyReservationCounts();

        $this->assertArrayHasKey('current_week', $result);
        $this->assertArrayHasKey('previous_week', $result);
        $this->assertSame(7, $result['current_week']);
    }

    // ─────────────────────────────────────────────────────────────
    // getMonthlyStats
    // ─────────────────────────────────────────────────────────────

    public function testGetMonthlyStatsReturnsStructure(): void
    {
        $reservationRow = [
            'total_reservations' => 10,
            'total_guests' => 30,
            'unique_users' => 8,
            'completed_reservations' => 7,
            'cancelled_reservations' => 2,
            'no_shows' => 1,
        ];
        $userRow = ['new_users' => 3];
        $reviewRow = ['total_reviews' => 5, 'avg_rating' => 4.2];

        $fetchIdx = 0;
        $fetchRows = [$reservationRow, $userRow, $reviewRow];

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturnCallback(
            static function () use (&$fetchIdx, $fetchRows) {
                return $fetchRows[$fetchIdx++] ?? false;
            }
        );

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getMonthlyStats(6, 2024);

        $this->assertArrayHasKey('reservations', $result);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertSame(10, $result['reservations']['total_reservations']);
    }

    // ─────────────────────────────────────────────────────────────
    // getCafePerformanceStats
    // ─────────────────────────────────────────────────────────────

    public function testGetCafePerformanceStatsReturnsRows(): void
    {
        $rows = [[
            'id' => 1,
            'name' => 'Neko',
            'type' => 'cat',
            'total_reservations' => 20,
            'total_guests' => 60,
            'completed' => 15,
            'cancelled' => 5,
            'completion_rate' => 75.0,
        ]];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getCafePerformanceStats('2024-01-01', '2024-06-30', 10);
        $this->assertCount(1, $result);
        $this->assertSame('Neko', $result[0]['name']);
    }

    public function testGetCafePerformanceStatsReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo([]);
        $repo = new StatisticsRepository($pdo);

        $this->assertSame([], $repo->getCafePerformanceStats('2024-01-01', '2024-01-31'));
    }

    // ─────────────────────────────────────────────────────────────
    // getReservationTrendStats
    // ─────────────────────────────────────────────────────────────

    public function testGetReservationTrendStatsReturnsRows(): void
    {
        $rows = [[
            'date' => '2024-06-01',
            'total_reservations' => 5,
            'total_guests' => 15,
            'completed' => 4,
            'cancelled' => 1,
        ]];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getReservationTrendStats('2024-06-01', '2024-06-30');
        $this->assertCount(1, $result);
    }

    public function testGetReservationTrendStatsReturnsEmpty(): void
    {
        $pdo = $this->makePdo([]);
        $repo = new StatisticsRepository($pdo);

        $this->assertSame([], $repo->getReservationTrendStats('2024-06-01', '2024-06-30'));
    }

    // ─────────────────────────────────────────────────────────────
    // getReservationsByCafeType
    // ─────────────────────────────────────────────────────────────

    public function testGetReservationsByCafeTypeReturnsRows(): void
    {
        $rows = [['type' => 'cat', 'total_reservations' => 30, 'total_guests' => 90, 'percentage' => 60.0]];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getReservationsByCafeType('2024-01-01', '2024-06-30');
        $this->assertCount(1, $result);
        $this->assertSame('cat', $result[0]['type']);
    }

    // ─────────────────────────────────────────────────────────────
    // getUserDistributionByRole
    // ─────────────────────────────────────────────────────────────

    public function testGetUserDistributionByRoleReturnsRows(): void
    {
        $rows = [['role_name' => 'Admin', 'role_code' => 'admin', 'user_count' => 2]];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getUserDistributionByRole();
        $this->assertCount(1, $result);
        $this->assertSame('admin', $result[0]['role_code']);
    }

    // ─────────────────────────────────────────────────────────────
    // getTopCafes
    // ─────────────────────────────────────────────────────────────

    public function testGetTopCafesReturnsRows(): void
    {
        $rows = [[
            'id' => 1,
            'name' => 'Neko Café',
            'type' => 'cat',
            'location' => 'Centro',
            'total_reservations' => 50,
            'total_guests' => 150,
            'avg_rating' => 4.5,
            'review_count' => 12,
        ]];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getTopCafes('2024-01-01', '2024-06-30', 5);
        $this->assertCount(1, $result);
    }

    public function testGetTopCafesReturnsEmpty(): void
    {
        $pdo = $this->makePdo([]);
        $repo = new StatisticsRepository($pdo);

        $this->assertSame([], $repo->getTopCafes('2024-01-01', '2024-01-31'));
    }

    // ─────────────────────────────────────────────────────────────
    // getCafeStats
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeStatsReturnsArray(): void
    {
        $row = ['total' => 5, 'active' => 4, 'with_reservations' => 3, 'categories' => 2, 'animal_types' => 2];
        $pdo = $this->makePdo([], $row);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getCafeStats();
        $this->assertIsArray($result);
        $this->assertSame(5, $result['total']);
    }

    public function testGetCafeStatsReturnsFalseWhenEmpty(): void
    {
        $pdo = $this->makePdo([], false);
        $repo = new StatisticsRepository($pdo);

        $this->assertFalse($repo->getCafeStats());
    }

    // ─────────────────────────────────────────────────────────────
    // getReportsSummary
    // ─────────────────────────────────────────────────────────────

    public function testGetReportsSummaryWithReservations(): void
    {
        // fetchColumn siempre devuelve '10'; avg_guests = round(10/10, 2) = 1.0
        $pdo = $this->makePdo([], false, '10');
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getReportsSummary('2024-01-01', '2024-06-30');

        $this->assertArrayHasKey('total_reservations', $result);
        $this->assertArrayHasKey('total_guests', $result);
        $this->assertArrayHasKey('avg_rating', $result);
        $this->assertArrayHasKey('active_users', $result);
        $this->assertArrayHasKey('avg_guests_per_reservation', $result);
        $this->assertSame(10, $result['total_reservations']);
        $this->assertSame(1.0, $result['avg_guests_per_reservation']);
    }

    public function testGetReportsSummaryWithZeroReservations(): void
    {
        // total_reservations = 0 → avg_guests = 0 (else branch)
        $pdo = $this->makePdo([], false, '0');
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getReportsSummary('2024-01-01', '2024-06-30');
        $this->assertSame(0, $result['avg_guests_per_reservation']);
    }

    // ─────────────────────────────────────────────────────────────
    // getDataViewerStats
    // ─────────────────────────────────────────────────────────────

    public function testGetDataViewerStatsReturnsAllKeys(): void
    {
        $pdo = $this->makePdo([], false, '20');
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getDataViewerStats();

        $keys = [
            'users',
            'staff',
            'cafes',
            'animals',
            'products',
            'reservations',
            'reservations_with_slot',
            'time_slots',
            'time_slots_available',
            'reviews',
            'incidents',
        ];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $result);
            $this->assertSame(20, $result[$key]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // getRecentReservations
    // ─────────────────────────────────────────────────────────────

    public function testGetRecentReservationsReturnsRows(): void
    {
        $rows = [[
            'id' => 1,
            'date' => '2024-06-01',
            'time_slot' => '10:00:00',
            'status' => 'confirmed',
            'guests' => 2,
            'cafe_name' => 'Neko',
            'customer_name' => 'Ana',
            'created_at' => '2024-06-01 09:00:00',
        ]];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getRecentReservations(10);
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getReservationsWithDetails
    // ─────────────────────────────────────────────────────────────

    public function testGetReservationsWithDetailsReturnsRows(): void
    {
        $rows = [[
            'id' => 1,
            'cafe_name' => 'Neko',
            'customer_name' => 'Ana',
            'customer_email' => 'ana@test.com',
            'reservation_date' => '2024-06-01',
        ]];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getReservationsWithDetails(100);
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getProductsWithCategories
    // ─────────────────────────────────────────────────────────────

    public function testGetProductsWithCategoriesReturnsRows(): void
    {
        $rows = [['id' => 1, 'name' => 'Entrada básica', 'category_name' => 'Entradas']];
        $pdo = $this->makePdo($rows);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getProductsWithCategories();
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getRecentActivity
    // ─────────────────────────────────────────────────────────────

    public function testGetRecentActivityCoversBranchesWhenRowsPresent(): void
    {
        // Un mismo stmt que devuelve siempre 1 fila con todos los campos necesarios
        $row = [
            'created_at' => '2024-06-01 10:00:00',
            'cafe_name' => 'Neko',
            'user_name' => 'Ana',
            'name' => 'Ana',
            'email' => 'ana@test.com',
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([$row]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new StatisticsRepository($pdo);
        $result = $repo->getRecentActivity(10);

        $this->assertCount(3, $result); // 1 reserva + 1 usuario + 1 reseña
        $types = \array_column($result, 'type');
        $this->assertContains('success', $types);
        $this->assertContains('info', $types);
        $this->assertContains('warning', $types);
    }

    public function testGetRecentActivityWithNoRowsReturnsEmpty(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new StatisticsRepository($pdo);
        $this->assertSame([], $repo->getRecentActivity(5));
    }

    // ─────────────────────────────────────────────────────────────
    // getReservationsChartData
    // ─────────────────────────────────────────────────────────────

    public function testGetReservationsChartDataReturnsSevenEntries(): void
    {
        $pdo = $this->makePdo([], false, '3');
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getReservationsChartData();

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('values', $result);
        $this->assertCount(7, $result['labels']);
        $this->assertCount(7, $result['values']);
        $this->assertSame(3, $result['values'][0]);
    }

    // ─────────────────────────────────────────────────────────────
    // getDataViewerSamples
    // ─────────────────────────────────────────────────────────────

    public function testGetDataViewerSamplesReturnsAllSections(): void
    {
        $pdo = $this->makePdo([]);
        $repo = new StatisticsRepository($pdo);

        $result = $repo->getDataViewerSamples();

        foreach (['cafes', 'products', 'staff', 'users', 'reservations', 'time_slots', 'reviews', 'incidents'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
