<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AuthAuditLog;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class AuthAuditLogTest extends TestCase
{
    /**
     * Constructor acepta ?PDO — siempre pasamos stub para evitar
     * que llame a Database::getConnection() con la BD real.
     * log(), getClientIp() y parseDeviceName() son estáticos y acceden a
     * Database::getConnection() / $_SERVER directamente → excluidos de tests unitarios.
     */
    private function makePdoWithPrepare(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    // ── findAll ───────────────────────────────────────────────────

    public function testFindAllReturnsPaginatedResult(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(5);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([
            ['id' => 1, 'event_type' => 'login', 'success' => 1],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = (new AuthAuditLog($pdo))->findAll();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(5, $result['total']);
    }

    public function testFindAllWithUserIdFilter(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(2);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([
            ['id' => 1, 'user_id' => 3, 'event_type' => 'login'],
            ['id' => 2, 'user_id' => 3, 'event_type' => 'logout'],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = (new AuthAuditLog($pdo))->findAll(['user_id' => 3]);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function testFindAllWithEventTypeFilter(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(1);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([
            ['id' => 7, 'event_type' => 'failed_login'],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = (new AuthAuditLog($pdo))->findAll(['event_type' => 'failed_login']);

        $this->assertSame(1, $result['total']);
    }

    public function testFindAllWithSuccessFilter(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(0);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = (new AuthAuditLog($pdo))->findAll(['success' => false]);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['data']);
    }

    public function testFindAllWithIpAddressFilter(): void
    {
        $stmtCount = $this->createStub(PDOStatement::class);
        $stmtCount->method('fetchColumn')->willReturn(3);

        $stmtData = $this->createStub(PDOStatement::class);
        $stmtData->method('fetchAll')->willReturn([
            ['id' => 1, 'ip_address' => '192.168.1.100'],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtCount, $stmtData);

        $result = (new AuthAuditLog($pdo))->findAll(['ip_address' => '192.168.1']);

        $this->assertSame(3, $result['total']);
    }

    // ── getStats ──────────────────────────────────────────────────

    public function testGetStatsReturnsStructuredArray(): void
    {
        $stmtTotals = $this->createStub(PDOStatement::class);
        $stmtTotals->method('fetch')->willReturn([
            'total_events' => 100,
            'successful_logins' => 80,
            'failed_logins' => 15,
            'lockouts' => 2,
            'unique_users' => 30,
            'unique_ips' => 25,
        ]);

        $stmtByType = $this->createStub(PDOStatement::class);
        $stmtByType->method('fetchAll')->willReturn([
            ['event_type' => 'login', 'count' => 80],
        ]);

        $stmtTopIps = $this->createStub(PDOStatement::class);
        $stmtTopIps->method('fetchAll')->willReturn([
            ['ip_address' => '10.0.0.1', 'count' => 20],
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtTotals, $stmtByType, $stmtTopIps);

        $result = (new AuthAuditLog($pdo))->getStats();

        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('events_by_type', $result);
        $this->assertArrayHasKey('top_ips', $result);
        $this->assertSame(100, $result['totals']['total_events']);
    }

    public function testGetStatsWithDateFilters(): void
    {
        $stmtTotals = $this->createStub(PDOStatement::class);
        $stmtTotals->method('fetch')->willReturn(['total_events' => 10]);

        $stmtByType = $this->createStub(PDOStatement::class);
        $stmtByType->method('fetchAll')->willReturn([]);

        $stmtTopIps = $this->createStub(PDOStatement::class);
        $stmtTopIps->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($stmtTotals, $stmtByType, $stmtTopIps);

        $result = (new AuthAuditLog($pdo))->getStats(['date_from' => '2024-01-01', 'date_to' => '2024-01-31']);

        $this->assertArrayHasKey('totals', $result);
    }

    // ── getRecentFailedLogins ─────────────────────────────────────

    public function testGetRecentFailedLoginsReturnsCount(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(7);

        $result = (new AuthAuditLog($this->makePdoWithPrepare($stmt)))->getRecentFailedLogins('192.168.1.1');

        $this->assertSame(7, $result);
    }

    public function testGetRecentFailedLoginsReturnsZeroWhenNone(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);

        $result = (new AuthAuditLog($this->makePdoWithPrepare($stmt)))->getRecentFailedLogins('10.0.0.1', 5);

        $this->assertSame(0, $result);
    }

    // ── getUserHistory ────────────────────────────────────────────

    public function testGetUserHistoryReturnsLogs(): void
    {
        $rows = [
            ['id' => 1, 'user_id' => 42, 'event_type' => 'login'],
            ['id' => 2, 'user_id' => 42, 'event_type' => 'logout'],
        ];
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($rows);

        $result = (new AuthAuditLog($this->makePdoWithPrepare($stmt)))->getUserHistory(42);

        $this->assertCount(2, $result);
        $this->assertSame('login', $result[0]['event_type']);
    }

    public function testGetUserHistoryReturnsEmptyWhenNone(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn([]);

        $result = (new AuthAuditLog($this->makePdoWithPrepare($stmt)))->getUserHistory(999);

        $this->assertSame([], $result);
    }

    // ── cleanup ───────────────────────────────────────────────────

    public function testCleanupReturnsRowCount(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn(42);

        $result = (new AuthAuditLog($this->makePdoWithPrepare($stmt)))->cleanup(90);

        $this->assertSame(42, $result);
    }

    public function testCleanupWithDefaultDaysToKeep(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn(0);

        $result = (new AuthAuditLog($this->makePdoWithPrepare($stmt)))->cleanup();

        $this->assertSame(0, $result);
    }
}
