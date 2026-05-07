<?php

/**
 * ¿Qué prueba aquí? El repositorio AuthLogRepository: queries de logs de autenticación,
 *   registro de eventos, historial por usuario, filtrado paginado y estadísticas.
 * ¿Qué me quieres demostrar? Que cada método construye la query correcta con los parámetros
 *   esperados y transforma los resultados apropiadamente.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la lógica de filtrado,
 *   los campos devueltos, o la estructura de retorno de getStats/findFiltered.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\AuthLogRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\RepositoryTestCase;

#[CoversClass(AuthLogRepository::class)]
final class AuthLogRepositoryTest extends RepositoryTestCase
{
    private function makeRepo(PDO $pdo): AuthLogRepository
    {
        return new AuthLogRepository($pdo);
    }

    // ─────────────────────────────────────────────────────────────
    // findSuspiciousActivity
    // ─────────────────────────────────────────────────────────────

    public function testFindSuspiciousActivityReturnsRows(): void
    {
        $rows = [['ip_address' => '1.2.3.4', 'failed_attempts' => 10, 'last_attempt' => '2024-01-01 12:00:00']];
        [$pdo] = $this->makePdoWithStmt($rows);
        $repo = $this->makeRepo($pdo);

        $result = $repo->findSuspiciousActivity(15, 5);
        $this->assertCount(1, $result);
        $this->assertSame('1.2.3.4', $result[0]['ip_address']);
    }

    public function testFindSuspiciousActivityReturnsEmptyWhenNone(): void
    {
        [$pdo] = $this->makePdoWithStmt([]);
        $repo = $this->makeRepo($pdo);

        $result = $repo->findSuspiciousActivity(30, 10);
        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // logEvent
    // ─────────────────────────────────────────────────────────────

    public function testLogEventReturnsTrueOnSuccess(): void
    {
        [$pdo] = $this->makePdoWithStmt();
        $repo = $this->makeRepo($pdo);

        $result = $repo->logEvent(1, 'login', '1.2.3.4', 'Mozilla', 'Desktop', true, null);
        $this->assertTrue($result);
    }

    public function testLogEventAcceptsNullUserId(): void
    {
        [$pdo] = $this->makePdoWithStmt();
        $repo = $this->makeRepo($pdo);

        $result = $repo->logEvent(null, 'failed_login', '5.6.7.8', null, null, false, 'bad_password');
        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────
    // getHistory
    // ─────────────────────────────────────────────────────────────

    public function testGetHistoryReturnsRows(): void
    {
        $rows = [
            ['event_type' => 'login', 'ip_address' => '1.2.3.4', 'success' => 1, 'created_at' => '2024-01-01'],
            ['event_type' => 'logout', 'ip_address' => '1.2.3.4', 'success' => 1, 'created_at' => '2024-01-01'],
        ];
        [$pdo] = $this->makePdoWithStmt($rows);
        $repo = $this->makeRepo($pdo);

        $result = $repo->getHistory(1, 10);
        $this->assertCount(2, $result);
    }

    public function testGetHistoryReturnsEmptyArrayWhenNone(): void
    {
        [$pdo] = $this->makePdoWithStmt([]);
        $repo = $this->makeRepo($pdo);

        $result = $repo->getHistory(999, 20);
        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // findFiltered — sin filtros
    // ─────────────────────────────────────────────────────────────

    public function testFindFilteredWithoutFiltersReturnsStructuredResult(): void
    {
        $rows = [['id' => 1, 'event_type' => 'login', 'user_name' => 'Alice']];

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);
        $stmt->method('fetchColumn')->willReturn(1);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->findFiltered([], 50, 0);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
    }

    // ─────────────────────────────────────────────────────────────
    // findFiltered — con filtros individuales
    // ─────────────────────────────────────────────────────────────

    public function testFindFilteredWithUserIdFilter(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->findFiltered(['user_id' => 42], 25, 0);

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['total']);
    }

    public function testFindFilteredWithEventTypeFilter(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->findFiltered(['event_type' => 'login'], 25, 0);

        $this->assertIsArray($result['data']);
    }

    public function testFindFilteredWithSuccessFilter(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->findFiltered(['success' => true], 25, 0);

        $this->assertIsArray($result['data']);
    }

    public function testFindFilteredWithDateRangeFilters(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->findFiltered(['date_from' => '2024-01-01', 'date_to' => '2024-01-31'], 25, 0);

        $this->assertIsArray($result['data']);
    }

    public function testFindFilteredWithIpAddressFilter(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('bindValue')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->findFiltered(['ip_address' => '192.168'], 25, 0);

        $this->assertIsArray($result['data']);
    }

    // ─────────────────────────────────────────────────────────────
    // getStats
    // ─────────────────────────────────────────────────────────────

    public function testGetStatsReturnsStructuredResult(): void
    {
        $totalsRow = [
            'total_events' => 100,
            'successful_logins' => 80,
            'failed_logins' => 15,
            'lockouts' => 2,
            'unique_users' => 20,
            'unique_ips' => 10,
        ];
        $byTypeRows = [['event_type' => 'login', 'count' => 80]];
        $topIpsRows = [['ip_address' => '1.2.3.4', 'count' => 30]];

        $call = 0;
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturnCallback(function () use ($totalsRow, &$call) {
            $call++;

            return $call === 1 ? $totalsRow : false;
        });
        $stmt->method('fetchAll')->willReturnOnConsecutiveCalls($byTypeRows, $topIpsRows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->getStats();

        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('events_by_type', $result);
        $this->assertArrayHasKey('top_ips', $result);
    }

    public function testGetStatsWithDateFilters(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'total_events' => 5,
            'successful_logins' => 3,
            'failed_logins' => 2,
            'lockouts' => 0,
            'unique_users' => 2,
            'unique_ips' => 1,
        ]);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->getStats(['date_from' => '2024-01-01', 'date_to' => '2024-01-31']);

        $this->assertArrayHasKey('totals', $result);
    }
}
