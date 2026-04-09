<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Services\RateLimitingService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests para RateLimitingService
 *
 * Verifica:
 * - Registro de intentos
 * - Verificación de límites
 * - Bloqueos temporales
 * - Reset de límites
 */
final class RateLimitingServiceTest extends TestCase
{
    private RateLimitingService $service;
    private PDO $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createStub(PDO::class);
        $this->service = new RateLimitingService($this->dbMock);
    }

    public function testRecordAttemptCreatesNewRecordWhenNotExists(): void
    {
        $stmtSelect = $this->createStub(PDOStatement::class);
        $stmtSelect->method('execute')->willReturn(true);
        $stmtSelect->method('fetch')->willReturn(false); // No existe

        $stmtInsert = $this->createStub(PDOStatement::class);
        $stmtInsert->method('execute')->willReturn(true);

        $this->dbMock->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtSelect, $stmtInsert);

        $result = $this->service->recordAttempt('login', 'test@example.com', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testRecordAttemptIncrementsExistingRecord(): void
    {
        $stmtSelect = $this->createStub(PDOStatement::class);
        $stmtSelect->method('execute')->willReturn(true);
        $stmtSelect->method('fetch')->willReturn(['id' => 1, 'attempt_count' => 2]);

        $stmtUpdate = $this->createStub(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $this->dbMock->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtSelect, $stmtUpdate);

        $result = $this->service->recordAttempt('login', 'test@example.com', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testIsBlockedReturnsFalseWhenNoRecordExists(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $result = $this->service->isBlocked('login', 'test@example.com');

        $this->assertIsArray($result);
        $this->assertFalse($result['blocked']);
    }

    public function testIsBlockedReturnsTrueWhenLockedUntilNotExpired(): void
    {
        $futureTime = date('Y-m-d H:i:s', time() + 600); // 10 minutos en el futuro

        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn([
            'attempt_count' => 5,
            'locked_until' => $futureTime
        ]);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $result = $this->service->isBlocked('login', 'test@example.com');

        $this->assertIsArray($result);
        $this->assertTrue($result['blocked']);
    }

    public function testGetRecentAttemptsReturnsCount(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(['attempt_count' => 3]);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $attempts = $this->service->getRecentAttempts('login', 'test@example.com');

        $this->assertEquals(3, $attempts);
    }

    public function testGetRecentAttemptsReturnsZeroWhenNoRecord(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $attempts = $this->service->getRecentAttempts('login', 'test@example.com');

        $this->assertEquals(0, $attempts);
    }

    public function testClearAttemptsDeletesRecord(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $result = $this->service->clearAttempts('login', 'test@example.com');

        $this->assertTrue($result);
    }

    public function testCleanupOldRecordsReturnsDeletedCount(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('rowCount')->willReturn(5);

        $this->dbMock->method('query')->willReturn($stmtMock);

        $deleted = $this->service->cleanupOldRecords();

        $this->assertEquals(5, $deleted);
    }
}
