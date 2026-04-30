<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AccountDeletionService::deleteAndAnonymize().
 * ¿Qué me quieres demostrar? Que el servicio retorna Result::fail cuando la capa de persistencia lanza excepción.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina el try/catch o cambia la firma de deleteAndAnonymize.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AccountDeletionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AccountDeletionService::class)]
final class AccountDeletionServiceTest extends TestCase
{
    private UserRepositoryInterface $userRepoStub;
    private AccountDeletionService $service;

    protected function setUp(): void
    {
        $this->userRepoStub = $this->createStub(UserRepositoryInterface::class);
        $this->service = new AccountDeletionService($this->userRepoStub);
    }

    public function testDeleteAndAnonymizeReturnsFailWhenRepoThrows(): void
    {
        $this->userRepoStub
            ->method('update')
            ->willThrowException(new RuntimeException('DB connection failed'));

        $result = $this->service->deleteAndAnonymize(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('No se pudo eliminar', $result->error);
    }

    public function testDeleteAndAnonymizeReturnsFailWhenAnonymizationThrows(): void
    {
        $this->userRepoStub
            ->method('anonymize')
            ->willThrowException(new RuntimeException('Anonymize failed'));

        $result = $this->service->deleteAndAnonymize(1);

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    public function testDeleteAndAnonymizeResultHasCorrectStructure(): void
    {
        $this->userRepoStub
            ->method('update')
            ->willThrowException(new RuntimeException('forced'));

        $result = $this->service->deleteAndAnonymize(42);

        $this->assertFalse($result->ok);
        $this->assertIsString($result->error);
    }
}
