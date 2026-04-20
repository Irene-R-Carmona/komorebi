<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * SessionManagementService: creación/revocación/actualización de sesiones activas,
 * limpieza de sesiones expiradas, registro de eventos de auditoría y consulta
 * del historial de autenticación.
 *
 * ¿Qué me quieres demostrar?
 * Que cada método delega correctamente a los repositorios inyectados,
 * retorna el valor exacto que devuelve el repositorio (bool, int, array, null)
 * y que los valores por defecto (reason, limit) se propagan al repositorio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se rompe la inyección de repositorios, si se cambia el tipo de retorno de algún
 * método, si los parámetros por defecto cambian de valor, o si la delegación
 * al repositorio correspondiente cambia.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\AuthLogRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\SessionManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionManagementService::class)]
final class SessionManagementServiceTest extends TestCase
{
    private SessionManagementService $service;
    /** @var SessionRepositoryInterface&MockObject */
    private SessionRepositoryInterface $sessionRepoMock;
    /** @var AuthLogRepositoryInterface&MockObject */
    private AuthLogRepositoryInterface $authLogRepoMock;

    protected function setUp(): void
    {
        $this->sessionRepoMock = $this->createMock(SessionRepositoryInterface::class);
        $this->authLogRepoMock = $this->createMock(AuthLogRepositoryInterface::class);
        $this->service = new SessionManagementService(
            $this->sessionRepoMock,
            $this->authLogRepoMock
        );
    }

    // ─────────────────────────────────────────────────────────────
    // createSession
    // ─────────────────────────────────────────────────────────────

    public function testCreateSessionReturnsTrueOnSuccess(): void
    {
        $this->sessionRepoMock->method('createOrUpdate')->willReturn(true);

        $result = $this->service->createSession(1, 'sess-abc', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testCreateSessionReturnsFalseWhenExecuteFails(): void
    {
        $this->sessionRepoMock->method('createOrUpdate')->willReturn(false);

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

        $this->sessionRepoMock->method('findActiveByUserId')->willReturn($rows);

        $result = $this->service->getActiveSessions(42);

        $this->assertCount(2, $result);
        $this->assertSame('abc', $result[0]['session_id']);
    }

    public function testGetActiveSessionsReturnsEmptyArrayWhenNoneFound(): void
    {
        $this->sessionRepoMock->method('findActiveByUserId')->willReturn([]);

        $result = $this->service->getActiveSessions(99);

        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getSessionById
    // ─────────────────────────────────────────────────────────────

    public function testGetSessionByIdReturnsRowWhenFound(): void
    {
        $row = ['id' => 7, 'user_id' => 3, 'session_id' => 'tok-xyz', 'ip_address' => '192.168.1.1'];

        $this->sessionRepoMock->method('findById')->willReturn($row);

        $result = $this->service->getSessionById(7);

        $this->assertSame($row, $result);
        $this->assertSame(7, $result['id']);
    }

    public function testGetSessionByIdReturnsNullWhenNotFound(): void
    {
        $this->sessionRepoMock->method('findById')->willReturn(null);

        $result = $this->service->getSessionById(9999);

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // updateSessionActivity
    // ─────────────────────────────────────────────────────────────

    public function testUpdateSessionActivityReturnsTrueOnSuccess(): void
    {
        $this->sessionRepoMock->method('updateActivity')->willReturn(true);

        $result = $this->service->updateSessionActivity('session-id-123');

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────
    // revokeSession
    // ─────────────────────────────────────────────────────────────

    public function testRevokeSessionReturnsTrueOnSuccess(): void
    {
        $this->sessionRepoMock->method('revoke')->willReturn(true);

        $result = $this->service->revokeSession(5, 3, 'admin_revoke');

        $this->assertTrue($result);
    }

    public function testRevokeSessionDefaultReasonIsUserRequested(): void
    {
        $this->sessionRepoMock
            ->expects($this->once())
            ->method('revoke')
            ->with(5, 3, 'user_requested')
            ->willReturn(true);

        $this->service->revokeSession(5, 3);
    }

    // ─────────────────────────────────────────────────────────────
    // revokeAllOtherSessions
    // ─────────────────────────────────────────────────────────────

    public function testRevokeAllOtherSessionsReturnsRevokedCount(): void
    {
        $this->sessionRepoMock->method('revokeAllExcept')->willReturn(3);

        $result = $this->service->revokeAllOtherSessions(1, 'current-sess', 1);

        $this->assertSame(3, $result);
    }

    public function testRevokeAllOtherSessionsReturnsZeroWhenNoneRevoked(): void
    {
        $this->sessionRepoMock->method('revokeAllExcept')->willReturn(0);

        $result = $this->service->revokeAllOtherSessions(1, 'only-session', 1);

        $this->assertSame(0, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // cleanupExpiredSessions
    // ─────────────────────────────────────────────────────────────

    public function testCleanupExpiredSessionsReturnsDeletedCount(): void
    {
        $this->sessionRepoMock->method('deleteExpired')->willReturn(5);

        $result = $this->service->cleanupExpiredSessions();

        $this->assertSame(5, $result);
    }

    public function testCleanupExpiredSessionsReturnsZeroWhenNothingDeleted(): void
    {
        $this->sessionRepoMock->method('deleteExpired')->willReturn(0);

        $result = $this->service->cleanupExpiredSessions();

        $this->assertSame(0, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // logAuthEvent
    // ─────────────────────────────────────────────────────────────

    public function testLogAuthEventReturnsTrueOnSuccess(): void
    {
        $this->authLogRepoMock->method('logEvent')->willReturn(true);

        $result = $this->service->logAuthEvent(1, 'login', '127.0.0.1');

        $this->assertTrue($result);
    }

    public function testLogAuthEventWithNullUserIdReturnsTrueOnSuccess(): void
    {
        $this->authLogRepoMock->method('logEvent')->willReturn(true);

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

    public function testLogAuthEventPassesSuccessFalseToRepo(): void
    {
        $this->authLogRepoMock
            ->expects($this->once())
            ->method('logEvent')
            ->with(1, 'login', '127.0.0.1', null, null, false, null)
            ->willReturn(true);

        $this->service->logAuthEvent(1, 'login', '127.0.0.1', null, null, false);
    }

    public function testLogAuthEventPassesSuccessTrueToRepo(): void
    {
        $this->authLogRepoMock
            ->expects($this->once())
            ->method('logEvent')
            ->with(1, 'login', '127.0.0.1', null, null, true, null)
            ->willReturn(true);

        $this->service->logAuthEvent(1, 'login', '127.0.0.1', null, null, true);
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

        $this->authLogRepoMock->method('getHistory')->willReturn($rows);

        $result = $this->service->getAuthHistory(1);

        $this->assertCount(2, $result);
        $this->assertSame('login', $result[0]['event_type']);
    }

    public function testGetAuthHistoryReturnsEmptyArrayWhenNoHistory(): void
    {
        $this->authLogRepoMock->method('getHistory')->willReturn([]);

        $result = $this->service->getAuthHistory(999);

        $this->assertSame([], $result);
    }

    public function testGetAuthHistoryDefaultLimitIsApplied(): void
    {
        $this->authLogRepoMock
            ->expects($this->once())
            ->method('getHistory')
            ->with(1, 20)
            ->willReturn([]);

        $result = $this->service->getAuthHistory(1);

        $this->assertSame([], $result);
    }
}
