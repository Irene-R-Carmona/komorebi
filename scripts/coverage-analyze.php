<?php

declare(strict_types=1);

$xml = simplexml_load_file('/app/tests/reports/coverage.xml');
$all = [];
$totalStmts = 0;
$totalCovered = 0;

foreach ($xml->project->package as $pkg) {
    foreach ($pkg->file as $file) {
        foreach ($file->class as $class) {
            $m = $class->metrics->attributes();
            $stmts = (int)$m['statements'];
            $covered = (int)$m['coveredstatements'];
            $totalStmts += $stmts;
            $totalCovered += $covered;
            if ($stmts > 0) {
                $pct = round($covered / $stmts * 100, 1);
                $all[] = [$pct, $stmts, $covered, ($stmts - $covered), (string)$class['name']];
            }
        }
    }
}

usort($all, function ($a, $b) {
    return $b[3] - $a[3];
});

echo "Total: $totalStmts, Covered: $totalCovered, %: " . round($totalCovered / $totalStmts * 100, 2) . "\n";
echo "Needed for 85%: " . ceil($totalStmts * 0.85) . ", Gap: " . (ceil($totalStmts * 0.85) - $totalCovered) . "\n\n";
echo "TOP 40 CLASSES BY UNCOVERED STATEMENTS:\n";
echo str_repeat('-', 85) . "\n";

foreach (array_slice($all, 0, 40) as $l) {
    printf("%-65s %5.1f%%  uncov=%d/%d\n", $l[4], $l[0], $l[3], $l[1]);
}

// Group by namespace
echo "\n\nBY NAMESPACE:\n";
echo str_repeat('-', 50) . "\n";
$namespaces = [];
foreach ($all as $l) {
    $parts = explode('\\', $l[4]);
    $ns = count($parts) >= 3 ? $parts[0] . '\\' . $parts[1] . '\\' . $parts[2] : implode('\\', $parts);
    if (!isset($namespaces[$ns])) {
        $namespaces[$ns] = ['stmts' => 0, 'covered' => 0];
    }
    $namespaces[$ns]['stmts'] += $l[1];
    $namespaces[$ns]['covered'] += $l[2];
}
arsort($namespaces);
uasort($namespaces, function ($a, $b) {
    return ($b['stmts'] - $b['covered']) - ($a['stmts'] - $a['covered']);
});
foreach ($namespaces as $ns => $data) {
    $pct = $data['stmts'] > 0 ? round($data['covered'] / $data['stmts'] * 100, 1) : 0;
    $uncov = $data['stmts'] - $data['covered'];
    printf("%-55s %5.1f%%  uncov=%d/%d\n", $ns, $pct, $uncov, $data['stmts']);
}
