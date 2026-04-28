<?php

declare(strict_types=1);

/**
 * OPcache Preload Script — Komorebi Café
 *
 * Compila en OPcache las clases más usadas por request durante el arranque de
 * FrankenPHP, antes de que llegue el primer request. Reduce la latencia de frío
 * (cold-start) del primer request tras reiniciar el proceso.
 *
 * Activado en docker/php/ini/opcache.ini:
 *   opcache.preload = /app/scripts/opcache-preload.php
 *   opcache.preload_user = www-data
 *
 * Referencia: https://www.php.net/manual/en/opcache.preloading.php
 */

$baseDir = \dirname(__DIR__) . '/app';

// Directorios a precargar (orden importa: Core → contratos → servicios → DTOs)
$dirs = [
    $baseDir . '/Core',
    $baseDir . '/Domain/DTO',
    $baseDir . '/Repositories/Contracts',
    $baseDir . '/Services/Contracts',
    $baseDir . '/Http/Transformers',
    $baseDir . '/Http/Middleware',
    $baseDir . '/Domain/ValueObjects',
];

$compiled = 0;
$errors   = [];

foreach ($dirs as $dir) {
    if (!\is_dir($dir)) {
        continue;
    }

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );

    /** @var \SplFileInfo $file */
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        if (\opcache_compile_file($path)) {
            $compiled++;
        } else {
            $errors[] = $path;
        }
    }
}

if ($errors !== []) {
    \error_log('[opcache-preload] Failed to compile ' . \count($errors) . ' file(s): ' . \implode(', ', $errors));
}
