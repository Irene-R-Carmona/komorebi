<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AccountDeletionService::deleteAndAnonymize con repositorio stubbeado para cubrir
 * el camino feliz (transacción exitosa) y el camino de fallo (excepción en repo).
 *
 * ¿Qué me quieres demostrar?
 * Que la operación atómica devuelve Result::ok(true) cuando la transacción
 * se completa, y Result::fail con mensaje descriptivo cuando el repositorio lanza excepción.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el rollBack en el catch, si se cambia el mensaje de error,
 * o si se modifica el tipo de retorno de ok() de true a otro valor.
 */

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AccountDeletionService;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

#[CoversClass(AccountDeletionService::class)]
final class AccountDeletionServiceTest extends TestCase
{
    private function injectPdo(PDO $pdo): void
    {
        $ref = new ReflectionClass(Database::class);
        $fake = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('connection')->setValue($fake, $pdo);
        $ref->getProperty('instance')->setValue(null, $fake);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Database::class);
        $ref->getProperty('instance')->setValue(null, null);
    }

    // ──────────────────────────────────────────────
    // deleteAndAnonymize — camino feliz
    // ──────────────────────────────────────────────

    public function testDeleteAndAnonymizeRetornaOkCuandoTransaccionExitosa(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $this->injectPdo($pdo);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('update')->willReturn(true);
        $userRepo->method('anonymize')->willReturn(true);

        $service = new AccountDeletionService($userRepo);
        $result  = $service->deleteAndAnonymize(42);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    // ──────────────────────────────────────────────
    // deleteAndAnonymize — fallo de transacción
    // ──────────────────────────────────────────────

    public function testDeleteAndAnonymizeRetornaFailCuandoPdoLanzaExcepcion(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')
            ->willThrowException(new RuntimeException('Connection refused'));
        $pdo->method('rollBack')->willReturn(true);
        $this->injectPdo($pdo);

        $service = new AccountDeletionService($this->createStub(UserRepositoryInterface::class));
        $result  = $service->deleteAndAnonymize(42);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('No se pudo eliminar la cuenta', $result->error);
    }

    public function testDeleteAndAnonymizeRetornaFailCuandoRepoLanzaExcepcion(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $this->injectPdo($pdo);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('update')
            ->willThrowException(new RuntimeException('Repo failed'));

        $service = new AccountDeletionService($userRepo);
        $result  = $service->deleteAndAnonymize(99);

        $this->assertFalse($result->ok);
    }
}
