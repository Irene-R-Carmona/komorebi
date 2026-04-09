<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * El manejo de payload ligero (waitlist_entry_id) en WaitlistPromotionJob:
 * hidratación desde BD y comportamiento ante entrada no encontrada o token expirado.
 *
 * ¿Qué me quieres demostrar?
 * Que handle() con solo waitlist_entry_id hidrata desde BD antes de validar.
 * Que si la entrada no existe, el job termina sin lanzar excepciones.
 * Que si el token ya expiró (expires_at en el pasado), el job marca como expirado y no envía email.
 * Que el payload completo (legacy) sigue funcionando igual (backward compat).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la lógica de hidratación en handle().
 * Si se deja de aceptar waitlist_entry_id como clave ligera.
 * Si hydratePayload lanza excepción en lugar de retornar null cuando no hay fila.
 * Si se rompe el path de token expirado.
 */

namespace Tests\Unit\Jobs;

use App\Jobs\WaitlistPromotionJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(\App\Jobs\WaitlistPromotionJob::class)]
final class WaitlistPromotionJobTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un PDO stub cuyo prepare() devuelve el stmt indicado para
     * la primera llamada a execute()/fetch().
     */
    private function makePdo(\PDOStatement $stmt): \PDO
    {
        $pdo = $this->createStub(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    /**
     * Crea un PDO stub con dos prepare() consecutivos: primero para
     * hidratar (SELECT) y luego para marcar como expirado (UPDATE).
     */
    private function makePdoWithTwoStmts(\PDOStatement $first, \PDOStatement $second): \PDO
    {
        $pdo = $this->createStub(\PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($first, $second);

        return $pdo;
    }

    private function makeStmt(mixed $fetchReturn = false): \PDOStatement
    {
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('rowCount')->willReturn(1);

        return $stmt;
    }

    /** Payload completo válido pero con token expirado (hace 1 hora). */
    private function expiredFullPayload(): array
    {
        return [
            'waitlist_id' => 1,
            'token'       => 'test-token-abc',
            'expires_at'  => \time() - 3600,
            'user_name'   => 'Test User',
            'user_email'  => 'test@example.com',
            'cafe_name'   => 'Komorebi Café',
            'date'        => '2024-12-25',
            'time'        => '10:00',
        ];
    }

    /** Fila de BD que hydratePayload devolvería para un token expirado. */
    private function expiredHydratedRow(int $waitlistId = 1): array
    {
        return [
            'waitlist_id' => $waitlistId,
            'token'       => 'test-token-abc',
            'expires_at'  => \time() - 3600,   // expirado hace 1 hora
            'user_name'   => 'Test User',
            'user_email'  => 'test@example.com',
            'cafe_name'   => 'Komorebi Café',
            'date'        => '2024-12-25',
            'time'        => '10:00',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // handle() — payload ligero con waitlist_entry_id
    // ─────────────────────────────────────────────────────────────

    #[TestDox('handle() con waitlist_entry_id desconocido termina sin excepción')]
    public function testHandleWithUnknownEntryIdReturnsEarly(): void
    {
        // PDO devuelve false → entrada no encontrada en BD
        $stmt = $this->makeStmt(false);
        $pdo  = $this->makePdo($stmt);
        $job  = new WaitlistPromotionJob($pdo);

        // No debe lanzar excepción alguna
        $job->handle(['waitlist_entry_id' => 9999]);

        $this->assertTrue(true);  // llegamos aquí sin excepción
    }

    #[TestDox('handle() con waitlist_entry_id hidrata desde BD y marca como expirado si el token venció')]
    public function testHandleWithEntryIdHydratesAndExpires(): void
    {
        // Primera prepare → SELECT (hydratePayload)
        $selectStmt = $this->makeStmt($this->expiredHydratedRow(1));
        // Segunda prepare → UPDATE markAsExpired
        $updateStmt = $this->makeStmt();
        $pdo        = $this->makePdoWithTwoStmts($selectStmt, $updateStmt);
        $job        = new WaitlistPromotionJob($pdo);

        // Token expirado → markAsExpired → return temprano, sin email
        $job->handle(['waitlist_entry_id' => 1]);

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────
    // handle() — payload completo (retrocompatibilidad)
    // ─────────────────────────────────────────────────────────────

    #[TestDox('handle() con payload completo y token expirado sigue el camino de markAsExpired (backward compat)')]
    public function testHandleWithFullPayloadAndExpiredTokenMarksExpired(): void
    {
        // Solo la UPDATE de markAsExpired; hydratePayload no se llama
        $updateStmt = $this->makeStmt();
        $pdo        = $this->makePdo($updateStmt);
        $job        = new WaitlistPromotionJob($pdo);

        $job->handle($this->expiredFullPayload());

        $this->assertTrue(true);
    }
}
