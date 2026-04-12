<?php

declare(strict_types=1);

// Remove UTF-8 BOM from view files that have it
$viewsDir = __DIR__ . '/../resources/views';
$bom = "\xEF\xBB\xBF";
$fixed = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    if (str_starts_with($content, $bom)) {
        file_put_contents($path, substr($content, 3));
        $fixed[] = $path;
        echo "Fixed BOM: $path\n";
    }
}

echo count($fixed) . " files fixed.\n";
