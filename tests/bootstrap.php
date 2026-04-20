<?php

declare(strict_types=1);

/**
 * Bootstrap de tests — PHPUnit 13 + PHP 8.4
 *
 * Responsabilidades:
 *   1. Cargar el autoloader de Composer.
 *   2. Activar reporte completo de errores (E_ALL).
 *   3. Cargar helpers de soporte de tests/Support/ de forma recursiva.
 *
 * Lo que YA NO hace este bootstrap (delegado a phpunit.xml):
 *   - set_error_handler manual: PHPUnit 13 gestiona la conversión de
 *     notices/warnings/deprecations vía convertDeprecationsToExceptions,
 *     convertNoticesToExceptions y convertWarningsToExceptions en phpunit.xml.
 *     El handler manual causaba conflictos con el sistema propio de PHPUnit 13
 *     y convertía deprecaciones de PHP 8.4 en ErrorException en cascada.
 *   - Inserción automática de docblocks: era peligroso en paratest
 *     (escritura en disco durante ejecución paralela) y generaba docblocks
 *     duplicados en tests que ya los tenían.
 *   - register_shutdown_function para validar docblocks: generaba exit codes
 *     espurios en CI con paratest (múltiples workers con backtrace vacío).
 */

require __DIR__ . '/../vendor/autoload.php';

// Reportar todos los errores — PHPUnit 13 los convierte según phpunit.xml.
error_reporting(E_ALL);

// Cargar todos los helpers de soporte en tests/Support/ de forma recursiva.
$supportDir = __DIR__ . '/Support';
if (is_dir($supportDir)) {
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($supportDir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        /** @var \SplFileInfo $file */
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            require_once $file->getPathname();
        }
    }
}
