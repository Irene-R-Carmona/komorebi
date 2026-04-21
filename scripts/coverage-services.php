<?php

declare(strict_types=1);

$xml = simplexml_load_file('tests/reports/coverage.xml');
$totalStmt = 0;
$coveredStmt = 0;
$rows = [];

foreach ($xml->project->package as $pkg) {
    foreach ($pkg->file as $file) {
        $name = (string)$file['name'];
        if (strpos($name, '/app/Services/') !== false && strpos($name, '/Contracts/') === false) {
            $m = $file->metrics;
            $stmts = (int)$m['statements'];
            $covered = (int)$m['coveredstatements'];
            $totalStmt += $stmts;
            $coveredStmt += $covered;
            $pct = $stmts > 0 ? round($covered / $stmts * 100, 1) : 0;
            $rows[] = [$pct, basename($name), $covered, $stmts];
        }
    }
}

usort($rows, fn($a, $b) => $a[0] <=> $b[0]);

echo str_pad('Coverage', 10) . str_pad('File', 45) . "Covered/Total\n";
echo str_repeat('-', 70) . "\n";

foreach ($rows as [$pct, $fname, $covered, $stmts]) {
    $flag = $pct < 85 ? ' <-- BELOW 85%' : '';
    echo str_pad($pct . '%', 10) . str_pad($fname, 45) . "$covered/$stmts$flag\n";
}

echo "\n";
$total = $totalStmt > 0 ? round($coveredStmt / $totalStmt * 100, 1) : 0;
echo "TOTAL app/Services: $coveredStmt/$totalStmt = $total%\n";
echo ($total >= 85 ? "TARGET MET (>= 85%)" : "BELOW TARGET (< 85%)") . "\n";
