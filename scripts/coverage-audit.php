<?php

declare(strict_types=1);

/**
 * Parsea coverage.xml y muestra cobertura por clase ordenada de menor a mayor.
 * Uso: php scripts/coverage-audit.php [ruta-al-coverage.xml]
 */

$coverageFile = $argv[1] ?? '/app/tests/reports/coverage.xml';

if (!file_exists($coverageFile)) {
    fwrite(STDERR, "ERROR: No se encontró: {$coverageFile}\n");
    exit(1);
}

$xml = simplexml_load_file($coverageFile);
$classes = [];

// Buscar clases en <package><class> y en <file><class>
foreach ($xml->project->children() as $node) {
    if ($node->getName() === 'package') {
        foreach ($node->class ?? [] as $cls) {
            $m = $cls->metrics;
            $total = (int) $m['statements'];
            $covered = (int) $m['coveredstatements'];
            if ($total > 0) {
                $pct = round($covered / $total * 100, 1);
                $classes[] = [$pct, $total, $covered, (string) $cls['name']];
            }
        }
        foreach ($node->file ?? [] as $file) {
            foreach ($file->class ?? [] as $cls) {
                $m = $cls->metrics;
                $total = (int) $m['statements'];
                $covered = (int) $m['coveredstatements'];
                if ($total > 0) {
                    $pct = round($covered / $total * 100, 1);
                    $classes[] = [$pct, $total, $covered, (string) $cls['name']];
                }
            }
        }
    } elseif ($node->getName() === 'file') {
        foreach ($node->class ?? [] as $cls) {
            $m = $cls->metrics;
            $total = (int) $m['statements'];
            $covered = (int) $m['coveredstatements'];
            if ($total > 0) {
                $pct = round($covered / $total * 100, 1);
                $classes[] = [$pct, $total, $covered, (string) $cls['name']];
            }
        }
    }
}

usort($classes, fn($a, $b) => $a[0] <=> $b[0]);

$filter = $argv[2] ?? 'all'; // 'zero', 'low' (<30%), 'all'

echo str_pad('Cov%', 8) . str_pad('Stmts', 7) . str_pad('Covered', 9) . "Class\n";
echo str_repeat('-', 80) . "\n";

foreach ($classes as [$pct, $total, $covered, $name]) {
    if ($filter === 'zero' && $pct > 0) {
        continue;
    }
    if ($filter === 'low' && $pct >= 30) {
        continue;
    }
    // Simplify class name
    $short = preg_replace('/^App\\\\/', '', $name);
    echo str_pad($pct . '%', 8)
        . str_pad((string) $total, 7)
        . str_pad((string) $covered, 9)
        . $short . "\n";
}

// Summary
$total0 = count(array_filter($classes, fn($c) => $c[0] == 0.0));
$totalLow = count(array_filter($classes, fn($c) => $c[0] > 0 && $c[0] < 30));
$totalMid = count(array_filter($classes, fn($c) => $c[0] >= 30 && $c[0] < 80));
$totalHigh = count(array_filter($classes, fn($c) => $c[0] >= 80));

echo "\n=== RESUMEN ===\n";
echo "0%      (sin cobertura):  {$total0} clases\n";
echo "<30%    (baja cobertura): {$totalLow} clases\n";
echo "30-80%  (media):          {$totalMid} clases\n";
echo ">=80%   (alta cobertura): {$totalHigh} clases\n";
echo "TOTAL:                    " . count($classes) . " clases\n";
