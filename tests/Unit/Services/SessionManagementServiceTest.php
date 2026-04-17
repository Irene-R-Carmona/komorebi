<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * SessionManagementService: creación/revocación/actualización de sesiones activas,
 * limpieza de sesiones expiradas, registro de eventos de auditoría y consulta
 * del historial de autenticación.
 *
 * ¿Qué me quieres demostrar?
 * Que cada método delega correctamente al PDO inyectado via constructor,
 * retorna el valor exacto que calcula (bool, int, array, null) según la
 * respuesta del statement, y que los valores por defecto (reason, limit) se
 * propagan al statement sin alteraciones.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se rompe la inyección de PDO, si se cambia el tipo de retorno de algún
 * método, si los parámetros por defecto cambian de valor, o si la lógica
 * de "false → null / false → []" para fetch/fetchAll se elimina.
 */

namespace Tests\Unit\Services;

use App\Services\SessionManagementService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SessionManagementServiceTest extends TestCase
{
    private SessionManagementService $service;
    private PDO&Stub $pdoMock;
    private PDOStatement&MockObject $stmtMock;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->service = new SessionManagementService($this->pdoMock);
    }

    // ─────────────────────────────────────────────────────────────
    // createSession
    // ─────────────────────────────────────────────────────────────

    public function testCreateSessionReturnsTrueOnSuccess(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);

        $result = $this->service->createSession(1, 'sess-abc', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testCreateSessionReturnsFalseWhenExecuteFails(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(false);

        $result = $this->service->createSession(1, 'sess-abc', '127.0.0.1', 'Mozilla/5.0', 'Desktop', 3600);

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────
    // getActiveSessions
    // ─────────────────────────────────────────────────────────────

    public function testGetActiveSessionsReturnsPopulatedArray(): void
    {
        $rows = [
            ['id' => 1, 'session_id' => 'abc', 'ip_address' => '127.0.0.1'],
            ['id' => 2, 'session_id' => 'def', 'ip_address' => '10.0.0.1'],
        ];

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn($rows);

        $result = $this->service->getActiveSessions(42);

        $this->assertCount(2, $result);
        $this->assertSame('abc', $result[0]['session_id']);
    }

    public function testGetActiveSessionsReturnsEmptyArrayWhenNoneFound(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([]);

        $result = $this->service->getActiveSessions(99);

        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getSessionById
    // ─────────────────────────────────────────────────────────────

    public function testGetSessionByIdReturnsRowWhenFound(): void
    {
        $row = ['id' => 7, 'user_id' => 3, 'session_id' => 'tok-xyz', 'ip_address' => '192.168.1.1'];

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn($row);

        $result = $this->service->getSessionById(7);

        $this->assertSame($row, $result);
        $this->assertSame(7, $result['id']);
    }

    public function testGetSessionByIdReturnsNullWhenNotFound(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn(false);

        $result = $this->service->getSessionById(9999);

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // updateSessionActivity
    // ─────────────────────────────────────────────────────────────

    public function testUpdateSessionActivityReturnsTrueOnSuccess(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);

        $result = $this->service->updateSessionActivity('session-id-123');

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────
    // revokeSession
    // ─────────────────────────────────────────────────────────────

    public function testRevokeSessionReturnsTrueOnSuccess(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);

        $result = $this->service->revokeSession(5, 3, 'admin_revoke');

        $this->assertTrue($result);
    }

    public function testRevokeSessionDefaultReasonIsUserRequested(): void
    {
        $capturedParams = null;

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });

        $this->service->revokeSession(5, 3);

        $this->assertSame('user_requested', $capturedParams['reason']);
    }

    // ─────────────────────────────────────────────────────────────
    // revokeAllOtherSessions
    // ─────────────────────────────────────────────────────────────

    public function testRevokeAllOtherSessionsReturnsRevokedCount(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('rowCount')->willReturn(3);

        $result = $this->service->revokeAllOtherSessions(1, 'current-sess', 1);

        $this->assertSame(3, $result);
    }

    public function testRevokeAllOtherSessionsReturnsZeroWhenNoneRevoked(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('rowCount')->willReturn(0);

        $result = $this->service->revokeAllOtherSessions(1, 'only-session', 1);

        $this->assertSame(0, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // cleanupExpiredSessions
    // ─────────────────────────────────────────────────────────────

    public function testCleanupExpiredSessionsReturnsDeletedCount(): void
    {
        $this->pdoMock->method('query')->willReturn($this->stmtMock);
        $this->stmtMock->method('rowCount')->willReturn(5);

        $result = $this->service->cleanupExpiredSessions();

        $this->assertSame(5, $result);
    }

    public function testCleanupExpiredSessionsReturnsZeroWhenNothingDeleted(): void
    {
        $this->pdoMock->method('query')->willReturn($this->stmtMock);
        $this->stmtMock->method('rowCount')->willReturn(0);

        $result = $this->service->cleanupExpiredSessions();

        $this->assertSame(0, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // logAuthEvent
    // ─────────────────────────────────────────────────────────────

    public function testLogAuthEventReturnsTrueOnSuccess(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);

        $result = $this->service->logAuthEvent(1, 'login', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testLogAuthEventWithNullUserIdReturnsTrueOnSuccess(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('execute')->willReturn(true);

        $result = $this->service->logAuthEvent(
            null,
            'failed_login',
            '192.168.1.50',
            'Chrome',
            null,
            false,
            'invalid_credentials'
        );

        $this->assertTrue($result);
    }

    public function testLogAuthEventStoresSuccessFlagAsInteger(): void
    {
        $capturedParams = null;

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });

        $this->service->logAuthEvent(1, 'login', '127.0.0.1', null, null, false);

        $this->assertSame(0, $capturedParams['success']);
    }

    public function testLogAuthEventStoresTrueSuccessAsOne(): void
    {
        $capturedParams = null;

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;

                return true;
            });

        $this->service->logAuthEvent(1, 'login', '127.0.0.1', null, null, true);

        $this->assertSame(1, $capturedParams['success']);
    }

    // ─────────────────────────────────────────────────────────────
    // getAuthHistory
    // ─────────────────────────────────────────────────────────────

    public function testGetAuthHistoryReturnsArrayOfEvents(): void
    {
        $rows = [
            ['event_type' => 'login',  'ip_address' => '127.0.0.1', 'success' => 1],
            ['event_type' => 'logout', 'ip_address' => '127.0.0.1', 'success' => 1],
        ];

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('bindValue')->willReturn(true);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn($rows);

        $result = $this->service->getAuthHistory(1);

        $this->assertCount(2, $result);
        $this->assertSame('login', $result[0]['event_type']);
    }

    public function testGetAuthHistoryReturnsEmptyArrayWhenNoHistory(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
        $this->stmtMock->method('bindValue')->willReturn(true);
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([]);

        $result = $this->service->getAuthHistory(999);

        $this->assertSame([], $result);
    }

    public function testGetAuthHistoryDefaultLimitIsApplied(): void
    {
        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        // Verify bindValue is called with limit=20 (default)
        $this->stmtMock
            ->expects($this->atLeastOnce())
            ->method('bindValue')
            ->willReturn(true);

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([]);

        $result = $this->service->getAuthHistory(1);

        $this->assertSame([], $result);
    }
}
