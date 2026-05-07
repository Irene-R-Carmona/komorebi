<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * RewardUnlockedJob necesita user_id en el payload y busca al usuario en la BD.
 * Si el usuario no existe, lanza excepción.
 *
 * ¿Qué me quieres demostrar?
 * Que handle() lanza Exception cuando falta user_id, y cuando el usuario no
 * existe en la base de datos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cambios en la validación del payload, en el mensaje de excepción, o en la
 * consulta SQL de búsqueda de usuario.
 */

namespace Tests\Unit\Jobs;

use App\Jobs\RewardUnlockedJob;
use Exception;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RewardUnlockedJob::class)]
final class RewardUnlockedJobTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Missing user_id
    // ──────────────────────────────────────────────────────────

    public function testHandleThrowsWhenUserIdIsMissing(): void
    {
        $pdo = $this->createStub(PDO::class);
        $job = new RewardUnlockedJob($pdo);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('user_id requerido');

        $job->handle([
            'stamps' => 5,
            'tier' => 'bronze',
            'milestone' => 5,
        ]);
    }

    public function testHandleThrowsWhenUserIdIsNull(): void
    {
        $pdo = $this->createStub(PDO::class);
        $job = new RewardUnlockedJob($pdo);

        $this->expectException(Exception::class);

        $job->handle([
            'user_id' => null,
            'stamps' => 5,
            'tier' => 'bronze',
            'milestone' => 5,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // User not found in DB
    // ──────────────────────────────────────────────────────────

    public function testHandleThrowsWhenUserNotFound(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $job = new RewardUnlockedJob($pdo);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Usuario no encontrado');

        $job->handle([
            'user_id' => 999,
            'stamps' => 5,
            'tier' => 'bronze',
            'milestone' => 5,
        ]);
    }

    public function testHandleExceptionIncludesUserId(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $job = new RewardUnlockedJob($pdo);

        try {
            $job->handle([
                'user_id' => 42,
                'stamps' => 5,
                'tier' => 'bronze',
                'milestone' => 5,
            ]);
            self::fail('Expected Exception');
        } catch (Exception $e) {
            self::assertStringContainsString('42', $e->getMessage());
        }
    }
}
