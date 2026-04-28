<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? SessionManagementService: revokeSessionForUser y revokeAllOtherSessions.
 * ¿Qué me quieres demostrar? Que la revocación falla si la sesión no pertenece al usuario.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la verificación de ownership en revokeSessionForUser.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\AuthLogRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\SessionManagementService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionManagementService::class)]
final class SessionManagementServiceTest extends TestCase
{
    private SessionRepositoryInterface $sessionRepoStub;
    private AuthLogRepositoryInterface $authLogRepoStub;
    private SessionManagementService $service;

    protected function setUp(): void
    {
        $this->sessionRepoStub = $this->createStub(SessionRepositoryInterface::class);
        $this->authLogRepoStub = $this->createStub(AuthLogRepositoryInterface::class);
        $this->service         = new SessionManagementService(
            $this->sessionRepoStub,
            $this->authLogRepoStub
        );
    }

    public function testRevokeSessionForUserReturnsFalseWhenSessionNotFound(): void
    {
        $this->sessionRepoStub->method('findById')->willReturn(null);

        $result = $this->service->revokeSessionForUser(1, 999);

        $this->assertFalse($result);
    }

    public function testRevokeSessionForUserReturnsFalseWhenSessionBelongsToDifferentUser(): void
    {
        $this->sessionRepoStub->method('findById')->willReturn(['id' => 5, 'user_id' => 99]);

        $result = $this->service->revokeSessionForUser(1, 5);

        $this->assertFalse($result);
    }

    public function testRevokeSessionForUserReturnsTrueWhenOwnerRevokes(): void
    {
        $this->sessionRepoStub->method('findById')->willReturn(['id' => 5, 'user_id' => 1]);
        $this->sessionRepoStub->method('revoke')->willReturn(true);

        $result = $this->service->revokeSessionForUser(1, 5);

        $this->assertTrue($result);
    }

    public function testGetActiveSessionsDelegatesToRepository(): void
    {
        $this->sessionRepoStub->method('findActiveByUserId')->willReturn([['id' => 1]]);

        $result = $this->service->getActiveSessions(1);

        $this->assertCount(1, $result);
    }
}
