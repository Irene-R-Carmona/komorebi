<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests del modelo AuditLog.
 *
 * Nota: el método estático AuditLog::log() usa Database::getConnection() directamente
 * y no es testeable unitariamente. Está cubierto en tests de integración.
 */
final class AuditLogTest extends TestCase
{
    private function stubPdoWithPrepare(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    // ── findAll ───────────────────────────────────────────────────

    public function testFindAllReturnsPaginatedResult(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(2);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([
            ['id' => 1, 'action' => 'create', 'old_values' => null, 'new_values' => null],
            ['id' => 2, 'action' => 'delete', 'old_values' => null, 'new_values' => null],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = new AuditLog($pdo)->findAll();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function testFindAllDecodesJsonValues(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(1);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'action' => 'update',
                'old_values' => '{"name":"Old"}',
                'new_values' => '{"name":"New"}',
            ],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = new AuditLog($pdo)->findAll();
        $this->assertSame(['name' => 'Old'], $result['data'][0]['old_values']);
        $this->assertSame(['name' => 'New'], $result['data'][0]['new_values']);
    }

    public function testFindAllWithUserIdFilter(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(1);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([
            ['id' => 1, 'action' => 'login', 'old_values' => null, 'new_values' => null],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = new AuditLog($pdo)->findAll(['user_id' => 5]);
        $this->assertSame(1, $result['total']);
    }

    public function testFindAllReturnsEmptyData(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(0);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = new AuditLog($pdo)->findAll(['action' => 'nonexistent']);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['data']);
    }

    // ── getStats ──────────────────────────────────────────────────

    public function testGetStatsReturnsStructuredArray(): void
    {
        $stmtTotals = $this->createStub(PDOStatement::class);
        $stmtTotals->method('fetch')->willReturn([
            'total_actions' => 100,
            'unique_users' => 10,
            'unique_ips' => 5,
            'last_24h' => 20,
            'critical_actions' => 3,
        ]);

        $stmtActions = $this->createStub(PDOStatement::class);
        $stmtActions->method('fetchAll')->willReturn([
            ['action' => 'create', 'count' => 50],
        ]);

        $stmtResources = $this->createStub(PDOStatement::class);
        $stmtResources->method('fetchAll')->willReturn([
            ['resource_type' => 'user', 'count' => 40],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtTotals, $stmtActions, $stmtResources);

        $result = new AuditLog($pdo)->getStats();
        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('top_actions', $result);
        $this->assertArrayHasKey('top_resources', $result);
        $this->assertSame(100, $result['totals']['total_actions']);
    }

    public function testGetStatsWithDateFilters(): void
    {
        $stmtTotals = $this->createStub(PDOStatement::class);
        $stmtTotals->method('fetch')->willReturn([
            'total_actions' => 5,
            'unique_users' => 2,
            'unique_ips' => 1,
            'last_24h' => 5,
            'critical_actions' => 0,
        ]);

        $stmtActions = $this->createStub(PDOStatement::class);
        $stmtActions->method('fetchAll')->willReturn([]);

        $stmtResources = $this->createStub(PDOStatement::class);
        $stmtResources->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtTotals, $stmtActions, $stmtResources);

        $result = new AuditLog($pdo)->getStats([
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
        ]);

        $this->assertArrayHasKey('totals', $result);
    }

    // ── getResourceHistory ────────────────────────────────────────

    public function testGetResourceHistoryReturnsLogs(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            ['id' => 1, 'action' => 'update', 'old_values' => null, 'new_values' => null],
        ]);

        $result = new AuditLog($this->stubPdoWithPrepare($stmt))->getResourceHistory('user', 1);
        $this->assertCount(1, $result);
        $this->assertSame('update', $result[0]['action']);
    }

    public function testGetResourceHistoryDecodesJsonValues(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([
            [
                'id' => 2,
                'action' => 'update',
                'old_values' => '{"status":"active"}',
                'new_values' => '{"status":"inactive"}',
            ],
        ]);

        $result = new AuditLog($this->stubPdoWithPrepare($stmt))->getResourceHistory('product', 3);
        $this->assertSame(['status' => 'active'], $result[0]['old_values']);
        $this->assertSame(['status' => 'inactive'], $result[0]['new_values']);
    }

    public function testGetResourceHistoryReturnsEmptyArrayWhenNone(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);

        $result = new AuditLog($this->stubPdoWithPrepare($stmt))->getResourceHistory('cafe', 999);
        $this->assertSame([], $result);
    }

    // ── cleanup ───────────────────────────────────────────────────

    public function testCleanupReturnsRowCount(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn(42);

        $result = new AuditLog($this->stubPdoWithPrepare($stmt))->cleanup(365);
        $this->assertSame(42, $result);
    }

    public function testCleanupWithDefaultDaysToKeep(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn(0);

        $result = new AuditLog($this->stubPdoWithPrepare($stmt))->cleanup();
        $this->assertSame(0, $result);
    }
}
