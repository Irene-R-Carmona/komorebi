<?php

declare(strict_types=1);

$cssRoot = '/app/public/css';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cssRoot));
$changed = [];

// Convierte valor alpha decimal a porcentaje entero
function alphaToPercent(string $alpha): string
{
    $f = (float) $alpha;
    $pct = (int) round($f * 100);

    return (string) $pct;
}

// Mapa: [r, g, b] → token
$colorMap = [
    '201,169,89' => '--color-acento',
    '92,61,46' => '--color-primario',
    '135,167,123' => '--color-time-ok',
    '247,243,235' => '--color-fondo',
];

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

    foreach ($colorMap as $rgb => $token) {
        [$r, $g, $b] = explode(',', $rgb);
        $pattern = '/rgba\(\s*' . $r . '\s*,\s*' . $g . '\s*,\s*' . $b . '\s*,\s*([\d.]+)\s*\)/';
        $updated = preg_replace_callback($pattern, static function (array $m) use ($token): string {
            $pct = alphaToPercent($m[1]);

            return 'color-mix(in srgb, var(' . $token . ') ' . $pct . '%, transparent)';
        }, $updated);
    }

    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $changed[] = str_replace('/app/', '', $path);
    }
}

echo 'Modificados: ' . count($changed) . PHP_EOL;
foreach (array_values($changed) as $c) {
    echo '  ' . $c . PHP_EOL;
}
