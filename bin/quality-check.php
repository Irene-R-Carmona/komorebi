#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Quality Check Runner
 *
 * Ejecuta todas las herramientas de calidad en secuencia
 * y genera un reporte consolidado.
 *
 * Uso: php bin/quality-check.php [--fix]
 */

$startTime = microtime(true);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  QUALITY CHECK - Komorebi Café PFC                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$autoFix = in_array('--fix', $argv, true);

if ($autoFix) {
    echo "🔧 Modo: AUTO-FIX ACTIVADO\n\n";
} else {
    echo "🔍 Modo: VERIFICACIÓN (usa --fix para corregir)\n\n";
}

$results = [];
$hasErrors = false;

// 1. Complejidad del código
echo "📊 [1/5] Analizando complejidad del código...\n";
$complexityOutput = [];
$complexityCode = 0;
exec('php ' . __DIR__ . '/analyze-complexity.php 2>&1', $complexityOutput, $complexityCode);

$criticalMethods = 0;
foreach ($complexityOutput as $line) {
    if (preg_match('/Críticos:\s*(\d+)/', $line, $matches)) {
        $criticalMethods = (int) $matches[1];
        break;
    }
}

$results['complexity'] = [
    'status' => $criticalMethods === 0 ? 'PASS' : 'FAIL',
    'critical_methods' => $criticalMethods,
    'message' => $criticalMethods === 0
        ? '✅ Sin métodos críticos'
        : "❌ $criticalMethods método(s) crítico(s) detectado(s)",
];

if ($criticalMethods > 0) {
    $hasErrors = true;
}

echo $results['complexity']['message'] . "\n\n";

// 2. PHP-CS-Fixer
echo "🎨 [2/5] Verificando estilo de código (PHP-CS-Fixer)...\n";

if ($autoFix) {
    exec('vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php 2>&1', $csFixerOutput, $csFixerCode);
    $results['cs-fixer'] = [
        'status' => 'FIXED',
        'message' => '✅ Estilo corregido automáticamente',
    ];
    echo $results['cs-fixer']['message'] . "\n\n";
} else {
    exec('vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run 2>&1', $csFixerOutput, $csFixerCode);
    $needsFixes = $csFixerCode !== 0;

    $results['cs-fixer'] = [
        'status' => $needsFixes ? 'NEEDS_FIX' : 'PASS',
        'message' => $needsFixes
            ? '⚠️  Estilo necesita corrección (ejecuta con --fix)'
            : '✅ Estilo correcto',
    ];

    if ($needsFixes) {
        $hasErrors = true;
    }

    echo $results['cs-fixer']['message'] . "\n\n";
}

// 3. PHPCS
echo "📏 [3/5] Verificando estándares PSR-12 (PHPCS)...\n";

if ($autoFix) {
    exec('vendor/bin/phpcbf --report=summary 2>&1', $phpcsOutput, $phpcsCode);
    $fixedCount = 0;
    foreach ($phpcsOutput as $line) {
        if (preg_match('/A TOTAL OF (\d+) ERROR/', $line, $matches)) {
            $fixedCount = (int) $matches[1];
            break;
        }
    }

    $results['phpcs'] = [
        'status' => 'FIXED',
        'fixed_count' => $fixedCount,
        'message' => "✅ $fixedCount violación(es) corregida(s)",
    ];
    echo $results['phpcs']['message'] . "\n\n";
} else {
    exec('vendor/bin/phpcs --report=summary 2>&1', $phpcsOutput, $phpcsCode);

    $errorCount = 0;
    $warningCount = 0;
    foreach ($phpcsOutput as $line) {
        if (preg_match('/A TOTAL OF (\d+) ERRORS? AND (\d+) WARNING/', $line, $matches)) {
            $errorCount = (int) $matches[1];
            $warningCount = (int) $matches[2];
            break;
        }
    }

    $results['phpcs'] = [
        'status' => $errorCount === 0 ? 'PASS' : 'NEEDS_FIX',
        'errors' => $errorCount,
        'warnings' => $warningCount,
        'message' => $errorCount === 0
            ? '✅ Sin violaciones PSR-12'
            : "⚠️  $errorCount error(es), $warningCount warning(s) (usa --fix)",
    ];

    if ($errorCount > 0) {
        $hasErrors = true;
    }

    echo $results['phpcs']['message'] . "\n\n";
}

// 4. PHPStan
echo "🔎 [4/5] Análisis estático (PHPStan)...\n";
exec('vendor/bin/phpstan analyse --memory-limit=1G --no-progress 2>&1', $phpstanOutput, $phpstanCode);

$phpstanErrors = 0;
foreach ($phpstanOutput as $line) {
    if (preg_match('/Found (\d+) error/', $line, $matches)) {
        $phpstanErrors = (int) $matches[1];
        break;
    }
}

$results['phpstan'] = [
    'status' => $phpstanCode === 0 ? 'PASS' : 'FAIL',
    'errors' => $phpstanErrors,
    'message' => $phpstanCode === 0
        ? '✅ Sin errores de tipo'
        : "❌ $phpstanErrors error(es) de tipo detectado(s)",
];

if ($phpstanCode !== 0) {
    $hasErrors = true;
}

echo $results['phpstan']['message'] . "\n\n";

// 5. Psalm
echo "🔬 [5/5] Análisis semántico (Psalm)...\n";
exec('vendor/bin/psalm --no-progress 2>&1', $psalmOutput, $psalmCode);

$psalmErrors = 0;
$psalmInfo = 0;
foreach ($psalmOutput as $line) {
    if (preg_match('/No errors found/', $line)) {
        $psalmErrors = 0;
        break;
    }
    if (preg_match('/(\d+) other issues? found/', $line, $matches)) {
        $psalmInfo = (int) $matches[1];
    }
}

$results['psalm'] = [
    'status' => $psalmErrors === 0 ? 'PASS' : 'FAIL',
    'errors' => $psalmErrors,
    'info' => $psalmInfo,
    'message' => $psalmErrors === 0
        ? "✅ Sin errores ($psalmInfo sugerencias info)"
        : "❌ $psalmErrors error(es) detectado(s)",
];

echo $results['psalm']['message'] . "\n\n";

// Resumen final
$duration = round(microtime(true) - $startTime, 2);

echo "════════════════════════════════════════════════════════════════\n";
echo "📈 RESUMEN:\n";
echo "────────────────────────────────────────────────────────────────\n";

foreach ($results as $tool => $result) {
    $icon = match ($result['status']) {
        'PASS' => '✅',
        'FIXED' => '🔧',
        'NEEDS_FIX' => '⚠️',
        'FAIL' => '❌',
        default => '❓'
    };

    $toolName = match ($tool) {
        'complexity' => 'Complejidad',
        'cs-fixer' => 'CS-Fixer',
        'phpcs' => 'PHPCS',
        'phpstan' => 'PHPStan',
        'psalm' => 'Psalm',
        default => $tool
    };

    echo sprintf("   %s %-15s %s\n", $icon, $toolName, $result['message']);
}

echo "────────────────────────────────────────────────────────────────\n";

if ($hasErrors) {
    echo "❌ QUALITY CHECK FALLÓ - Corrige los errores antes de continuar\n";
    if (!$autoFix) {
        echo "💡 Tip: Ejecuta con --fix para corregir automáticamente\n";
    }
    echo "════════════════════════════════════════════════════════════════\n\n";
    exit(1);
}
echo "✅ QUALITY CHECK PASÓ - Código listo para commit\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "⏱️  Tiempo total: {$duration}s\n\n";
exit(0);
