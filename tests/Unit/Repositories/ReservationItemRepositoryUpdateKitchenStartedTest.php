<?php

/**
 * ¿Qué pruebas aquí? ReservationItemRepository::updateKitchenStarted() — nuevo método de timestamp.
 * ¿Qué me quieres demostrar? Que el método ejecuta el SQL correcto y vincula el ID proporcionado.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambia la SQL (eliminando kitchen_started_at = NOW())
 *   o si el método deja de pasar el ID al execute().
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ReservationItemRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationItemRepository::class)]
final class ReservationItemRepositoryUpdateKitchenStartedTest extends TestCase
{
    public function testUpdateKitchenStartedExecutesCorrectSql(): void
    {
        $stmt = $this->createMock(PDOStatement::class);

        // El SQL debe contener el fragmento correcto
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SET kitchen_started_at = NOW()'))
            ->willReturn($stmt);

        // execute() debe recibir exactamente [7] (el ID pasado)
        $stmt->expects($this->once())
            ->method('execute')
            ->with([7]);

        $repo = new ReservationItemRepository($pdo);
        $repo->updateKitchenStarted(7);
    }

    public function testUpdateKitchenStartedBindsGivenId(): void
    {
        $capturedArgs = null;

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $args) use (&$capturedArgs): bool {
                $capturedArgs = $args;

                return true;
            });

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ReservationItemRepository($pdo);
        $repo->updateKitchenStarted(123);

        $this->assertSame([123], $capturedArgs);
    }
}
