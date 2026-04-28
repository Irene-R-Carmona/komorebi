<?php

declare(strict_types=1);

/**
 * Verifica que la cobertura de código sea >= 85%.
 * Lee el Clover XML generado por PHPUnit.
 *
 * Uso: php scripts/check-coverage.php [ruta-al-coverage.xml] [porcentaje-minimo]
 * Salida: 0 si cumple el umbral, 1 si no cumple o si el archivo no existe.
 */

$coverageFile = $argv[1] ?? '/app/tests/reports/coverage.xml';
$threshold    = isset($argv[2]) ? (float) $argv[2] : 85.0;

if (!\file_exists($coverageFile)) {
    \fwrite(\STDERR, "ERROR: No se encontró el archivo de cobertura: {$coverageFile}\n");
    exit(1);
}

$xml = \simplexml_load_file($coverageFile);

if ($xml === false) {
    \fwrite(\STDERR, "ERROR: No se pudo parsear el XML de cobertura: {$coverageFile}\n");
    exit(1);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    \fwrite(\STDERR, "ERROR: Formato inesperado — falta <metrics> en el XML de cobertura.\n");
    exit(1);
}

$attrs        = $metrics->attributes();
$totalStmts   = (int) ($attrs['statements'] ?? 0);
$coveredStmts = (int) ($attrs['coveredstatements'] ?? 0);

if ($totalStmts === 0) {
    \fwrite(\STDERR, "ERROR: El XML de cobertura reporta 0 statements — verifica que los tests se ejecutaron.\n");
    exit(1);
}

$actual = \round($coveredStmts / $totalStmts * 100, 2);
$needed = (int) \ceil($totalStmts * ($threshold / 100));
$gap    = $needed - $coveredStmts;

echo \sprintf(
    "Cobertura actual:  %.2f%% (%d/%d statements)\n",
    $actual,
    $coveredStmts,
    $totalStmts,
);
echo \sprintf("Umbral requerido:  %.2f%%\n", $threshold);

if ($actual < $threshold) {
    echo \sprintf(
        "FALLO: Faltan %d statements cubiertos para alcanzar el %.2f%%.\n",
        $gap,
        $threshold,
    );
    exit(1);
}

echo \sprintf("OK: Cobertura %.2f%% >= umbral %.2f%%.\n", $actual, $threshold);
exit(0);
