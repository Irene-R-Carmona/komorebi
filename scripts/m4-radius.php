<?php

declare(strict_types=1);

$cssRoot = '/app/public/css';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cssRoot));
$changed = [];

// Mapeo exacto px → token
$map = [
    '4px' => 'var(--radius-sm)',
    '8px' => 'var(--radius-md)',
    '12px' => 'var(--radius-lg)',
    '16px' => 'var(--radius-xl)',
    '24px' => 'var(--radius-2xl)',
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

    foreach ($map as $px => $token) {
        // Solo reemplaza border-radius: <valor_exacto> (único valor, sin multi-valor con espacios)
        // Matches: border-radius: 8px;   border-radius: 8px /* comentario */
        // No matches: border-radius: 8px 8px 0 0  (multi-valor → skip por seguridad)
        $updated = preg_replace(
            '/\bborder-radius:\s*' . preg_quote($px, '/') . '\s*(?=;|$|\/\*)/m',
            'border-radius: ' . $token,
            $updated
        );
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
