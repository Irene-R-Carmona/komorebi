<?php

declare(strict_types=1);

$cssRoot = '/app/public/css';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cssRoot));
$changed = [];

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'css') {
        continue;
    }
    $path = $file->getRealPath();
    if (str_contains($path, 'design-tokens')) {
        continue;
    }
    $original = file_get_contents($path);
    $updated = $original;

    // 0.2s con easing estándar → var(--transition-fast)
    $updated = preg_replace('/0\.2s cubic-bezier\(0\.4,\s*0,\s*0\.2,\s*1\)/', 'var(--transition-fast)', $updated);
    $updated = preg_replace('/0\.2s ease(?:-in-out|-out|-in)?/', 'var(--transition-fast)', $updated);
    $updated = preg_replace('/0\.2s(?=[,;\s])/', 'var(--transition-fast)', $updated);

    // 0.3s con easing estándar → var(--transition-base)
    $updated = preg_replace('/0\.3s cubic-bezier\(0\.4,\s*0,\s*0\.2,\s*1\)/', 'var(--transition-base)', $updated);
    $updated = preg_replace('/0\.3s ease(?:-in-out|-out|-in)?/', 'var(--transition-base)', $updated);
    $updated = preg_replace('/0\.3s(?=[,;\s])/', 'var(--transition-base)', $updated);

    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $changed[] = str_replace('/var/www/', '', $path);
    }
}

echo 'Modificados: ' . count($changed) . PHP_EOL;
foreach (array_values($changed) as $c) {
    echo '  ' . $c . PHP_EOL;
}
