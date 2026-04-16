<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * El comportamiento de LoggingPDOStatement al ejecutar queries: mide tiempo y loguea
 * solo cuando la duración supera el umbral establecido.
 *
 * ¿Qué me quieres demostrar?
 * Que el statement solo emite un log WARNING cuando ms >= slowMs, y no cuando es rápido.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la lógica de timing, si el umbral deja de respetarse, o si se cambia
 * el canal de log o el mensaje.
 */

namespace Tests\Unit\Core;

use App\Core\LoggingPDOStatement;
use PHPUnit\Framework\TestCase;

final class LoggingPDOStatementTest extends TestCase
{
    private function callTruncateSql(string $sql): string
    {
        $method = new \ReflectionMethod(LoggingPDOStatement::class, 'truncateSql');

        return (string) $method->invoke(null, $sql);
    }

    public function testTruncateSqlReturnsSqlUnchangedWhenShort(): void
    {
        $sql = 'SELECT * FROM cafes WHERE id = ?';

        $this->assertSame($sql, $this->callTruncateSql($sql));
    }

    public function testTruncateSqlCutsAt500Chars(): void
    {
        $sql = \str_repeat('X', 600);

        $result = $this->callTruncateSql($sql);

        $this->assertSame(500, \strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testTruncateSqlExactly500CharsUnchanged(): void
    {
        $sql = \str_repeat('Y', 500);

        $this->assertSame($sql, $this->callTruncateSql($sql));
    }
}
