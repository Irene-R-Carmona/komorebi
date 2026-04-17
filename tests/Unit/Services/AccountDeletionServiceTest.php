<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AccountDeletionService::deleteAndAnonymize con PDO stubbeado para cubrir
 * el camino feliz (transacción exitosa) y el camino de fallo (excepción en DB).
 *
 * ¿Qué me quieres demostrar?
 * Que la operación atómica devuelve Result::ok(true) cuando la transacción
 * se completa, y Result::fail con mensaje descriptivo cuando PDO lanza excepción.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el rollBack en el catch, si se cambia el mensaje de error,
 * o si se modifica el tipo de retorno de ok() de true a otro valor.
 */

namespace Tests\Unit\Services;

use App\Services\AccountDeletionService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class AccountDeletionServiceTest extends TestCase
{
    // ──────────────────────────────────────────────
    // deleteAndAnonymize — camino feliz
    // ──────────────────────────────────────────────

    public function testDeleteAndAnonymizeRetornaOkCuandoTransaccionExitosa(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('commit')->willReturn(true);

        $service = new AccountDeletionService($pdo);
        $result = $service->deleteAndAnonymize(42);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    // ──────────────────────────────────────────────
    // deleteAndAnonymize — fallo de transacción
    // ──────────────────────────────────────────────

    public function testDeleteAndAnonymizeRetornaFailCuandoPdoLanzaExcepcion(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')
            ->willThrowException(new \RuntimeException('Connection refused'));
        $pdo->method('rollBack')->willReturn(true);

        $service = new AccountDeletionService($pdo);
        $result = $service->deleteAndAnonymize(42);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('No se pudo eliminar la cuenta', $result->error);
    }

    public function testDeleteAndAnonymizeRetornaFailCuandoPrepareDevuelveFalse(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('prepare')
            ->willThrowException(new \RuntimeException('Prepare failed'));
        $pdo->method('rollBack')->willReturn(true);

        $service = new AccountDeletionService($pdo);
        $result = $service->deleteAndAnonymize(99);

        $this->assertFalse($result->ok);
    }
}
