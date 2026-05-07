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
echo "================================================================\n";
echo "  QUALITY CHECK - Komorebi Cafe PFC\n";
echo "================================================================\n";
echo "\n";

$autoFix = in_array('--fix', $argv, true);

if ($autoFix) {
    echo "[MODE] AUTO-FIX ACTIVADO\n\n";
} else {
    echo "[MODE] VERIFICACION (usa --fix para corregir)\n\n";
}

$results = [];
$hasErrors = false;

// 1. Complejidad del código
echo "[STEP] [1/5] Analizando complejidad del codigo...\n";
$complexityOutput = [];
$complexityCode = 0;
exec('php ' . __DIR__ . '/analyze-complexity.php 2>&1', $complexityOutput, $complexityCode);

$criticalMethods = 0;
foreach ($complexityOutput as $line) {
    if (preg_match('/Criticos:\s*(\d+)/', $line, $matches)) {
        $criticalMethods = (int) $matches[1];
        break;
    }
}

$results['complexity'] = [
    'status' => $criticalMethods === 0 ? 'PASS' : 'FAIL',
    'critical_methods' => $criticalMethods,
    'message' => $criticalMethods === 0
        ? '[OK] Sin metodos criticos'
        : "[FAIL] $criticalMethods metodo(s) critico(s) detectado(s)",
];

if ($criticalMethods > 0) {
    $hasErrors = true;
}

echo $results['complexity']['message'] . "\n\n";

// 2. PHP-CS-Fixer
echo "[STEP] [2/5] Verificando estilo de codigo (PHP-CS-Fixer)...\n";

if ($autoFix) {
    exec('vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php 2>&1', $csFixerOutput, $csFixerCode);
    $results['cs-fixer'] = [
        'status' => 'FIXED',
        'message' => '[OK] Estilo corregido automaticamente',
    ];
    echo $results['cs-fixer']['message'] . "\n\n";
} else {
    exec('vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run 2>&1', $csFixerOutput, $csFixerCode);
    $needsFixes = $csFixerCode !== 0;

    $results['cs-fixer'] = [
        'status' => $needsFixes ? 'NEEDS_FIX' : 'PASS',
        'message' => $needsFixes
            ? '[WARN] Estilo necesita correccion (ejecuta con --fix)'
            : '[OK] Estilo correcto',
    ];

    if ($needsFixes) {
        $hasErrors = true;
    }

    echo $results['cs-fixer']['message'] . "\n\n";
}

// 3. PHPCS
echo "[STEP] [3/5] Verificando estandares PSR-12 (PHPCS)...\n";

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
        'message' => "[OK] $fixedCount violacion(es) corregida(s)",
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
            ? '[OK] Sin violaciones PSR-12'
            : "[WARN] $errorCount error(es), $warningCount warning(s) (usa --fix)",
    ];

    if ($errorCount > 0) {
        $hasErrors = true;
    }

    echo $results['phpcs']['message'] . "\n\n";
}

// 4. PHPStan
echo "[STEP] [4/5] Analisis estatico (PHPStan)...\n";
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
        ? '[OK] Sin errores de tipo'
        : "[FAIL] $phpstanErrors error(es) de tipo detectado(s)",
];

if ($phpstanCode !== 0) {
    $hasErrors = true;
}

echo $results['phpstan']['message'] . "\n\n";

// 5. Tests (Psalm eliminado — decision 2026-04-16)
echo "[STEP] [5/5] Ejecutando tests (PHPUnit)...\n";
exec('vendor/bin/phpunit --no-coverage 2>&1', $phpunitOutput, $phpunitCode);

$phpunitFailed = 0;
foreach ($phpunitOutput as $line) {
    if (preg_match('/Failures:\s*(\d+)/', $line, $matches)) {
        $phpunitFailed += (int) $matches[1];
    }
    if (preg_match('/Errors:\s*(\d+)/', $line, $matches)) {
        $phpunitFailed += (int) $matches[1];
    }
}

$results['phpunit'] = [
    'status' => $phpunitCode === 0 ? 'PASS' : 'FAIL',
    'errors' => $phpunitFailed,
    'message' => $phpunitCode === 0
        ? '[OK] Todos los tests pasan'
        : "[FAIL] $phpunitFailed fallo(s) detectado(s)",
];

if ($phpunitCode !== 0) {
    $hasErrors = true;
}

echo $results['phpunit']['message'] . "\n\n";

// Resumen final
$duration = round(microtime(true) - $startTime, 2);

echo "================================================================\n";
echo "[STEP] RESUMEN:\n";
echo "----------------------------------------------------------------\n";

foreach ($results as $tool => $result) {
    $icon = match ($result['status']) {
        'PASS' => '[OK]  ',
        'FIXED' => '[FIX] ',
        'NEEDS_FIX' => '[WARN]',
        'FAIL' => '[FAIL]',
        default => '[?]   '
    };

    $toolName = match ($tool) {
        'complexity' => 'Complejidad',
        'cs-fixer' => 'CS-Fixer  ',
        'phpcs' => 'PHPCS     ',
        'phpstan' => 'PHPStan   ',
        'phpunit' => 'PHPUnit   ',
        default => $tool
    };

    echo sprintf("   %s %-15s %s\n", $icon, $toolName, $result['message']);
}

echo "----------------------------------------------------------------\n";

if ($hasErrors) {
    echo "[FAIL] QUALITY CHECK FALLO - Corrige los errores antes de continuar\n";
    if (!$autoFix) {
        echo "[TIP]  Ejecuta con --fix para corregir automaticamente\n";
    }
    echo "================================================================\n\n";
    exit(1);
}
echo "[OK] QUALITY CHECK PASO - Codigo listo para commit\n";
echo "================================================================\n";
echo "[TIME] Tiempo total: {$duration}s\n\n";
exit(0);
