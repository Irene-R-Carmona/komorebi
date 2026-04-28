<?php
$xml = simplexml_load_file("/app/coverage.xml");
$all = [];
$totalStmts = 0;
$totalCovered = 0;
foreach ($xml->project->package as $pkg) {
    foreach ($pkg->file as $file) {
        foreach ($file->class as $class) {
            $m = $class->metrics->attributes();
            $stmts = (int)$m["statements"];
            $covered = (int)$m["coveredstatements"];
            $totalStmts += $stmts;
            $totalCovered += $covered;
            if ($stmts > 0) {
                $pct = round($covered / $stmts * 100, 1);
                $uncovered = $stmts - $covered;
                $all[] = [$pct, $stmts, $covered, $uncovered, (string)$class["name"]];
            }
        }
    }
}
usort($all, function ($a, $b) {
    return $b[3] - $a[3];
});
echo "Total: $totalStmts, Covered: $totalCovered, %: " . round($totalCovered / $totalStmts * 100, 2) . "\n";
echo "Need85: " . ceil($totalStmts * 0.85) . ", Gap: " . (ceil($totalStmts * 0.85) - $totalCovered) . "\n\n";
foreach (array_slice($all, 0, 60) as $l) {
    echo sprintf("%-70s %5.1f%% cov=%d/%d uncov=%d\n", $l[4], $l[0], $l[2], $l[1], $l[3]);
}
