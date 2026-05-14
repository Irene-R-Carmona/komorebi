<?php

declare(strict_types=1);

/**
 * Tests de integración para --dry-run en apply-db.php
 *
 * ¿Qué pruebas aquí?
 * Ejecución real del script apply-db.php con el flag --dry-run en el contenedor.
 *
 * ¿Qué me quieres demostrar?
 * Que --dry-run sale con código 0, emite marcadores [DRY-RUN] y no ejecuta
 * ninguna migración SQL (el log "Aplicando:" no aparece con dry-run).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el flag --dry-run, si deja de salir con código 0, si los
 * marcadores [DRY-RUN] desaparecen del output, o si --dry-run empieza a
 * ejecutar migraciones reales.
 */

namespace Tests\Integration\Scripts;

use PHPUnit\Framework\TestCase;

final class ApplyDbDryRunTest extends TestCase
{
    private static string $scriptPath;

    public static function setUpBeforeClass(): void
    {
        self::$scriptPath = \realpath(__DIR__ . '/../../../scripts/apply-db.php') ?: '';
    }

    public function testDryRunExitsZero(): void
    {
        $output   = [];
        $exitCode = 0;
        \exec('php ' . \escapeshellarg(self::$scriptPath) . ' --dry-run --no-interaction 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode, 'dry-run debe salir con código 0');
    }

    public function testDryRunOutputContainsDryRunMarker(): void
    {
        $output = [];
        \exec('php ' . \escapeshellarg(self::$scriptPath) . ' --dry-run --no-interaction 2>&1', $output);
        $joined = \implode("\n", $output);

        self::assertStringContainsString('[DRY-RUN]', $joined, 'El output debe incluir marcadores [DRY-RUN]');
    }

    public function testDryRunDoesNotApplyMigrations(): void
    {
        $output = [];
        \exec('php ' . \escapeshellarg(self::$scriptPath) . ' --dry-run --no-interaction 2>&1', $output);
        $joined = \implode("\n", $output);

        self::assertStringNotContainsString(
            'Aplicando:',
            $joined,
            'dry-run no debe ejecutar migraciones (log "Aplicando:" no debe aparecer)',
        );
    }

    public function testDryRunCompletionMessagePresent(): void
    {
        $output = [];
        \exec('php ' . \escapeshellarg(self::$scriptPath) . ' --dry-run --no-interaction 2>&1', $output);
        $joined = \implode("\n", $output);

        self::assertStringContainsString(
            'No se ha modificado ningún dato ni esquema',
            $joined,
            'dry-run debe mostrar mensaje de cierre indicando que no se modificó nada',
        );
    }
}
