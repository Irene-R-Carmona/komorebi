<?php

declare(strict_types=1);

$xml = simplexml_load_file('/app/tests/reports/coverage.xml');
if (!$xml) {
    echo "No se pudo leer coverage.xml\n";
    exit(1);
}

$rows = [];
foreach ($xml->project->package as $pkg) {
    $pkgName = (string)$pkg['name'];
    if (strpos($pkgName, 'App\Services') !== 0) {
        continue;
    }
    foreach ($pkg->file as $file) {
        $m     = $file->metrics;
        $stmts = (int)$m['statements'];
        $cov   = (int)$m['coveredstatements'];
        $pct   = $stmts > 0 ? (int)round($cov / $stmts * 100) : 0;
        $base  = basename((string)$file['name'], '.php');
        $rows[] = [$pct, $cov, $stmts, $base];
    }
}

usort($rows, static fn($a, $b) => $a[0] <=> $b[0]);

echo str_pad('Service', 50) . str_pad('Cov%', 6) . str_pad('Covered', 10) . "Total\n";
echo str_repeat('-', 75) . "\n";
foreach ($rows as [$pct, $cov, $stmts, $name]) {
    echo str_pad($name, 50) . str_pad($pct . '%', 6) . str_pad((string)$cov, 10) . $stmts . "\n";
}
