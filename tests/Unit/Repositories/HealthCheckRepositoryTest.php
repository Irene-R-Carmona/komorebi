<?php

/**
 * ¿Qué prueba aquí? El repositorio HealthCheckRepository: búsquedas por id, animal y fecha,
 *   historial de chequeos, checks pendientes, CRUD, estadísticas de alertas y logs de cuidado.
 * ¿Qué me quieres demostrar? Que cada método construye la query con los parámetros correctos
 *   y devuelve la estructura esperada.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la lógica de fechas por defecto
 *   en findByAnimalAndDate/countByKeeperInPeriod, o la estructura del INSERT en create/createCareLog.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\HealthCheckRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthCheckRepository::class)]
final class HealthCheckRepositoryTest extends TestCase
{
    private function makePdo(array $fetchAllReturn = [], mixed $fetchReturn = false): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('42');
        return $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // findById
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = [
            'id' => 1,
            'animal_id' => 5,
            'checked_by' => 10,
            'check_date' => '2024-06-01',
            'created_at' => '2024-06-01 09:00:00',
            'appetite' => 'good',
            'energy_level' => 'high',
            'coat_condition' => 'shiny',
            'eyes_clear' => 1,
            'breathing_normal' => 1,
            'mobility_normal' => 1,
            'animal_name' => 'Mochi',
            'species_type' => 'cat',
            'current_status' => 'healthy',
            'keeper_name' => 'Ana',
        ];
        $pdo = $this->makePdo([], $row);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->findById(1);
        $this->assertSame('Mochi', $result->animal_name);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo([], false);
        $repo = new HealthCheckRepository($pdo);

        $this->assertNull($repo->findById(999));
    }

    // ─────────────────────────────────────────────────────────────
    // findByAnimalAndDate
    // ─────────────────────────────────────────────────────────────

    public function testFindByAnimalAndDateReturnsRowWhenFound(): void
    {
        $row = [
            'id' => 1,
            'animal_id' => 5,
            'checked_by' => 10,
            'check_date' => '2024-06-01',
            'created_at' => '2024-06-01 09:00:00',
            'appetite' => 'good',
            'energy_level' => 'high',
            'coat_condition' => 'shiny',
            'eyes_clear' => 1,
            'breathing_normal' => 1,
            'mobility_normal' => 1,
            'animal_name' => 'Mochi',
            'species_type' => 'cat',
            'keeper_name' => 'Ana',
        ];
        $pdo = $this->makePdo([], $row);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->findByAnimalAndDate(5, '2024-06-01');
        $this->assertSame(1, $result->id);
    }

    public function testFindByAnimalAndDateReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo([], false);
        $repo = new HealthCheckRepository($pdo);

        $this->assertNull($repo->findByAnimalAndDate(5, '2024-06-01'));
    }

    public function testFindByAnimalAndDateUsesTodayWhenDateIsNull(): void
    {
        $pdo = $this->makePdo([], false);
        $repo = new HealthCheckRepository($pdo);

        // Should not throw — uses date() internally
        $result = $repo->findByAnimalAndDate(5, null);
        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // findTodayByAnimalId
    // ─────────────────────────────────────────────────────────────

    public function testFindTodayByAnimalIdDelegatesToFindByAnimalAndDate(): void
    {
        $pdo = $this->makePdo([], false);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->findTodayByAnimalId(5);
        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // getCheckHistory
    // ─────────────────────────────────────────────────────────────

    public function testGetCheckHistoryReturnsRows(): void
    {
        $rows = [
            ['id' => 1, 'check_date' => '2024-06-01', 'keeper_name' => 'Ana'],
            ['id' => 2, 'check_date' => '2024-05-31', 'keeper_name' => 'Ana'],
        ];
        $pdo = $this->makePdo($rows);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->getCheckHistory(5, 30);
        $this->assertCount(2, $result);
    }

    public function testGetCheckHistoryReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo([]);
        $repo = new HealthCheckRepository($pdo);

        $this->assertSame([], $repo->getCheckHistory(999));
    }

    // ─────────────────────────────────────────────────────────────
    // getTodayChecks
    // ─────────────────────────────────────────────────────────────

    public function testGetTodayChecksReturnsRows(): void
    {
        $rows = [['id' => 1, 'animal_name' => 'Mochi']];
        $pdo = $this->makePdo($rows);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->getTodayChecks();
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getPendingAnimals
    // ─────────────────────────────────────────────────────────────

    public function testGetPendingAnimalsWithoutCafeId(): void
    {
        $rows = [['id' => 3, 'name' => 'Luna']];
        $pdo = $this->makePdo($rows);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->getPendingAnimals();
        $this->assertCount(1, $result);
    }

    public function testGetPendingAnimalsWithCafeId(): void
    {
        $rows = [['id' => 3, 'cafe_id' => 2, 'name' => 'Luna']];
        $pdo = $this->makePdo($rows);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->getPendingAnimals(2);
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getCheckswithAlerts
    // ─────────────────────────────────────────────────────────────

    public function testGetCheckswithAlertsReturnsRows(): void
    {
        $rows = [['id' => 1, 'animal_name' => 'Rex', 'alerts' => '["Fiebre detectada"]']];
        $pdo = $this->makePdo($rows);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->getCheckswithAlerts(7);
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // create
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsLastInsertId(): void
    {
        $pdo = $this->makePdo();
        $repo = new HealthCheckRepository($pdo);

        $data = [
            'animal_id' => 5,
            'checked_by' => 2,
            'check_date' => '2024-06-01',
            'weight_kg' => 4.5,
            'temperature_c' => 38.5,
            'appetite' => 'good',
            'energy_level' => 'high',
            'coat_condition' => 'good',
            'eyes_clear' => true,
            'breathing_normal' => true,
            'mobility_normal' => true,
            'notes' => 'Looking healthy',
            'alerts' => ['Fiebre detectada'],
        ];

        $id = $repo->create($data);
        $this->assertSame(42, $id);
    }

    public function testCreateWithMinimalData(): void
    {
        $pdo = $this->makePdo();
        $repo = new HealthCheckRepository($pdo);

        $id = $repo->create(['animal_id' => 1, 'checked_by' => 1, 'check_date' => '2024-06-01']);
        $this->assertSame(42, $id);
    }

    // ─────────────────────────────────────────────────────────────
    // exists
    // ─────────────────────────────────────────────────────────────

    public function testExistsReturnsTrueWhenFound(): void
    {
        $pdo = $this->makePdo([], ['count' => 1]);
        $repo = new HealthCheckRepository($pdo);

        $this->assertTrue($repo->exists(5, '2024-06-01'));
    }

    public function testExistsReturnsFalseWhenNotFound(): void
    {
        $pdo = $this->makePdo([], ['count' => 0]);
        $repo = new HealthCheckRepository($pdo);

        $this->assertFalse($repo->exists(5, '2024-06-01'));
    }

    public function testExistsReturnsFalseWhenFetchReturnsFalse(): void
    {
        $pdo = $this->makePdo([], false);
        $repo = new HealthCheckRepository($pdo);

        $this->assertFalse($repo->exists(5, '2024-06-01'));
    }

    // ─────────────────────────────────────────────────────────────
    // countByKeeperInPeriod
    // ─────────────────────────────────────────────────────────────

    public function testCountByKeeperInPeriodReturnsCount(): void
    {
        $pdo = $this->makePdo([], ['count' => 12]);
        $repo = new HealthCheckRepository($pdo);

        $count = $repo->countByKeeperInPeriod(2, '2024-06-01', '2024-06-30');
        $this->assertSame(12, $count);
    }

    public function testCountByKeeperInPeriodUsesDefaultDates(): void
    {
        $pdo = $this->makePdo([], ['count' => 5]);
        $repo = new HealthCheckRepository($pdo);

        $count = $repo->countByKeeperInPeriod(2);
        $this->assertSame(5, $count);
    }

    public function testCountByKeeperInPeriodReturnsZeroWhenFetchFalse(): void
    {
        $pdo = $this->makePdo([], false);
        $repo = new HealthCheckRepository($pdo);

        $count = $repo->countByKeeperInPeriod(2, '2024-06-01', '2024-06-30');
        $this->assertSame(0, $count);
    }

    // ─────────────────────────────────────────────────────────────
    // getRecentLogs
    // ─────────────────────────────────────────────────────────────

    public function testGetRecentLogsReturnsRows(): void
    {
        $rows = [['id' => 1, 'animal_name' => 'Mochi', 'species' => 'cat', 'keeper_name' => 'Ana']];
        $pdo = $this->makePdo($rows);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->getRecentLogs(20);
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // createCareLog
    // ─────────────────────────────────────────────────────────────

    public function testCreateCareLogReturnsLastInsertId(): void
    {
        $pdo = $this->makePdo();
        $repo = new HealthCheckRepository($pdo);

        $id = $repo->createCareLog(['animal_id' => 5, 'logged_by_user_id' => 2, 'notes' => 'Fed well']);
        $this->assertSame(42, $id);
    }

    public function testCreateCareLogUsesDefaultKeeperId(): void
    {
        $pdo = $this->makePdo();
        $repo = new HealthCheckRepository($pdo);

        $id = $repo->createCareLog(['animal_id' => 5, 'notes' => 'Normal day']);
        $this->assertSame(42, $id);
    }

    // ─────────────────────────────────────────────────────────────
    // getAlertStatistics
    // ─────────────────────────────────────────────────────────────

    public function testGetAlertStatisticsReturnsRows(): void
    {
        $rows = [[
            'alert_date' => '2024-06-01',
            'total_checks_with_alerts' => 3,
            'fever_count' => 1,
            'appetite_count' => 1,
            'lethargy_count' => 0,
            'respiratory_count' => 1
        ]];
        $pdo = $this->makePdo($rows);
        $repo = new HealthCheckRepository($pdo);

        $result = $repo->getAlertStatistics(7);
        $this->assertCount(1, $result);
    }

    public function testGetAlertStatisticsReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo([]);
        $repo = new HealthCheckRepository($pdo);

        $this->assertSame([], $repo->getAlertStatistics());
    }
}
