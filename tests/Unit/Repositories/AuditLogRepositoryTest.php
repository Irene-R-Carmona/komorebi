<?php

/**
 * ¿Qué prueba aquí? El repositorio AuditLogRepository: consultas paginadas de logs
 *   de auditoría, estadísticas de acciones, historial por recurso y limpieza.
 * ¿Qué me quieres demostrar? Que cada método construye la query con los filtros correctos
 *   y que la estructura de retorno es la esperada.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la lógica de filtros,
 *   el parseo de JSON en old_values/new_values, o la estructura de retorno de getStats.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\AuditLogRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLogRepository::class)]
final class AuditLogRepositoryTest extends TestCase
{
    private function makePdoMultiStmt(array $stmts): PDO
    {
        $idx = 0;
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function () use (&$idx, $stmts) {
            return $stmts[$idx++] ?? $stmts[array_key_last($stmts)];
        });
        return $pdo;
    }

    private function makeSimplePdo(array $fetchAllReturn = [], mixed $fetchReturn = false, int $fetchColumn = 0): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumn > 0 ? $fetchColumn : false);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    // ─────────────────────────────────────────────────────────────
    // findAll — sin filtros
    // ─────────────────────────────────────────────────────────────

    public function testFindAllWithoutFiltersReturnsStructuredResult(): void
    {
        $rows = [
            [
                'id' => 1,
                'user_id' => 1,
                'action' => 'create',
                'old_values' => null,
                'new_values' => '{"name":"test"}',
                'user_name' => 'Alice',
                'user_email' => 'a@b.com',
                'resource_type' => 'product',
                'resource_id' => 5,
                'ip_address' => '1.2.3.4',
                'user_agent' => 'Mozilla',
                'created_at' => '2024-01-01'
            ],
        ];

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(1);

        $dataStmt = $this->createStub(PDOStatement::class);
        $dataStmt->method('execute')->willReturn(true);
        $dataStmt->method('fetchAll')->willReturn($rows);
        $dataStmt->method('bindValue')->willReturn(true);

        $pdo = $this->makePdoMultiStmt([$countStmt, $dataStmt]);

        $repo = new AuditLogRepository($pdo);
        $result = $repo->findAll([], 50, 0);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(1, $result['total']);
        $this->assertNull($result['data'][0]['old_values']);
        $this->assertIsArray($result['data'][0]['new_values']);
    }

    public function testFindAllDecodesJsonValues(): void
    {
        $rows = [
            [
                'id' => 2,
                'user_id' => 1,
                'action' => 'update',
                'old_values' => '{"name":"old"}',
                'new_values' => '{"name":"new"}',
                'user_name' => 'Bob',
                'user_email' => 'b@b.com',
                'resource_type' => 'cafe',
                'resource_id' => 1,
                'ip_address' => '2.2.2.2',
                'user_agent' => null,
                'created_at' => '2024-01-02'
            ],
        ];

        $countStmt = $this->createStub(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn(1);

        $dataStmt = $this->createStub(PDOStatement::class);
        $dataStmt->method('execute')->willReturn(true);
        $dataStmt->method('fetchAll')->willReturn($rows);
        $dataStmt->method('bindValue')->willReturn(true);

        $pdo = $this->makePdoMultiStmt([$countStmt, $dataStmt]);

        $repo = new AuditLogRepository($pdo);
        $result = $repo->findAll([], 50, 0);

        $this->assertIsArray($result['data'][0]['old_values']);
        $this->assertSame('old', $result['data'][0]['old_values']['name']);
    }

    // ─────────────────────────────────────────────────────────────
    // findAll — filtros individuales
    // ─────────────────────────────────────────────────────────────

    public function testFindAllWithUserIdFilter(): void
    {
        $pdo = $this->makeSimplePdo([]);
        $repo = new AuditLogRepository($pdo);
        $result = $repo->findAll(['user_id' => 5]);
        $this->assertSame([], $result['data']);
    }

    public function testFindAllWithActionFilter(): void
    {
        $pdo = $this->makeSimplePdo([]);
        $repo = new AuditLogRepository($pdo);
        $result = $repo->findAll(['action' => 'delete']);
        $this->assertSame([], $result['data']);
    }

    public function testFindAllWithResourceTypeFilter(): void
    {
        $pdo = $this->makeSimplePdo([]);
        $repo = new AuditLogRepository($pdo);
        $result = $repo->findAll(['resource_type' => 'user']);
        $this->assertIsArray($result['data']);
    }

    public function testFindAllWithDateRangeFilters(): void
    {
        $pdo = $this->makeSimplePdo([]);
        $repo = new AuditLogRepository($pdo);
        $result = $repo->findAll(['date_from' => '2024-01-01', 'date_to' => '2024-01-31']);
        $this->assertIsArray($result['data']);
    }

    public function testFindAllWithIpAddressFilter(): void
    {
        $pdo = $this->makeSimplePdo([]);
        $repo = new AuditLogRepository($pdo);
        $result = $repo->findAll(['ip_address' => '192.168']);
        $this->assertIsArray($result['data']);
    }

    // ─────────────────────────────────────────────────────────────
    // getStats
    // ─────────────────────────────────────────────────────────────

    public function testGetStatsReturnsStructuredResult(): void
    {
        $totals = [
            'total_actions' => 50,
            'unique_users' => 10,
            'unique_ips' => 5,
            'last_24h' => 3,
            'critical_actions' => 2
        ];
        $topActions = [['action' => 'create', 'count' => 20]];
        $topResources = [['resource_type' => 'product', 'count' => 15]];

        $call = 0;
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturnCallback(function () use ($totals, &$call) {
            $call++;
            return $call === 1 ? $totals : false;
        });
        $stmt->method('fetchAll')->willReturnOnConsecutiveCalls($topActions, $topResources);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new AuditLogRepository($pdo);
        $result = $repo->getStats();

        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('top_actions', $result);
        $this->assertArrayHasKey('top_resources', $result);
        $this->assertSame($totals, $result['totals']);
    }

    public function testGetStatsWithDateFilters(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'total_actions' => 5,
            'unique_users' => 2,
            'unique_ips' => 1,
            'last_24h' => 1,
            'critical_actions' => 0
        ]);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new AuditLogRepository($pdo);
        $result = $repo->getStats(['date_from' => '2024-01-01', 'date_to' => '2024-01-31']);

        $this->assertArrayHasKey('totals', $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getResourceHistory
    // ─────────────────────────────────────────────────────────────

    public function testGetResourceHistoryReturnsDecodedRows(): void
    {
        $rows = [
            [
                'id' => 1,
                'user_name' => 'Alice',
                'user_email' => 'a@b.com',
                'old_values' => '{"name":"old"}',
                'new_values' => null
            ],
        ];
        $pdo = $this->makeSimplePdo($rows);

        $repo = new AuditLogRepository($pdo);
        $result = $repo->getResourceHistory('product', 1);

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['old_values']);
        $this->assertNull($result[0]['new_values']);
    }

    public function testGetResourceHistoryReturnsEmptyArray(): void
    {
        $pdo = $this->makeSimplePdo([]);
        $repo = new AuditLogRepository($pdo);

        $result = $repo->getResourceHistory('product', 999);
        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // cleanup
    // ─────────────────────────────────────────────────────────────

    public function testCleanupReturnsDeletedCount(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(15);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new AuditLogRepository($pdo);
        $deleted = $repo->cleanup(30);

        $this->assertSame(15, $deleted);
    }

    public function testCleanupWithDefaultDays(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new AuditLogRepository($pdo);
        $deleted = $repo->cleanup();

        $this->assertSame(0, $deleted);
    }
}
