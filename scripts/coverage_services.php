<?php

declare(strict_types=1);

$xmlFile = $argv[1] ?? '/app/tests/reports/coverage.xml';
$xml = simplexml_load_file($xmlFile);
$total = 0;
$covered = 0;
$byService = [];

foreach ($xml->project->package as $pkg) {
    $name = (string) $pkg['name'];
    if (strpos($name, 'Services') === false || strpos($name, 'Contracts') !== false) {
        continue;
    }
    foreach ($pkg->file as $file) {
        $m = $file->metrics;
        $stmts = (int) $m['statements'];
        $cov   = (int) $m['coveredstatements'];
        $total += $stmts;
        $covered += $cov;
        $pct = $stmts > 0 ? round($cov / $stmts * 100, 1) : 0.0;
        $byService[$name][] = [
            'file'    => basename((string) $file['name']),
            'stmts'   => $stmts,
            'covered' => $cov,
            'pct'     => $pct,
        ];
    }
}

echo "\n=== COBERTURA app/Services/ ===\n\n";
foreach ($byService as $pkg => $files) {
    foreach ($files as $f) {
        $bar = $f['pct'] >= 85 ? '[OK ]' : ($f['pct'] >= 50 ? '[MED]' : '[LOW]');
        printf(
            "  %s %-50s %5.1f%% (%d/%d)\n",
            $bar,
            $f['file'],
            $f['pct'],
            $f['covered'],
            $f['stmts']
        );
    }
}

$pct = $total > 0 ? round($covered / $total * 100, 1) : 0.0;
echo "\nTOTAL Services: {$covered}/{$total} = {$pct}%\n";
echo ($pct >= 85 ? ">>> OBJETIVO 85% ALCANZADO <<<\n" : ">>> FALTA " . round(85 - $pct, 1) . "% para alcanzar 85% <<<\n");
