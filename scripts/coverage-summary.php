<?php
// Parse Clover XML coverage report and show summary
$file = '/tmp/coverage.xml';
if (!file_exists($file)) {
    echo "No coverage file found at $file\n";
    exit(1);
}
$xml = simplexml_load_file($file);
$metrics = $xml->project->metrics;
$stmts   = (int)$metrics['statements'];
$covered = (int)$metrics['coveredstatements'];
$pct     = $stmts > 0 ? round($covered / $stmts * 100, 2) : 0;
echo "Total statements : $stmts\n";
echo "Covered          : $covered\n";
echo "Coverage         : $pct%\n";
echo "Gap to 85%       : " . max(0, (int)ceil($stmts * 0.85) - $covered) . " statements\n";
$rows = [];
// Files can be at project level or nested in packages
$allFiles = [];
foreach ($xml->project->file as $f) {
    $allFiles[] = $f;
}
foreach ($xml->project->package as $pkg) {
    foreach ($pkg->file as $f) {
        $allFiles[] = $f;
    }
}
foreach ($allFiles as $f) {
    $m = $f->metrics;
    $s = (int)$m['statements'];
    $c = (int)$m['coveredstatements'];
    if ($s === 0) continue;
    $p = round($c / $s * 100, 1);
    $name = str_replace('/app/', '', (string)$f['name']);
    $rows[] = [$p, $c, $s, $name];
}
usort($rows, fn($a, $b) => $a[0] <=> $b[0]);
echo "\n=== By file (sorted by coverage %, lowest first) ===\n";
foreach ($rows as [$p, $c, $s, $name]) {
    echo sprintf("%5.1f%%  %4d/%4d  %s\n", $p, $c, $s, $name);
}
