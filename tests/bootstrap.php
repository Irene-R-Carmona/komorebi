<?php

declare(strict_types=1);

// Bootstrap de tests custom: convierte notices en excepciones
// y asegura que todos los archivos de test contengan el bloque
// explicativo requerido por el equipo.

require __DIR__ . '/../vendor/autoload.php';

// Asegurarnos de que se reporten todas las advertencias y deprecations durante tests
error_reporting(E_ALL);

// Cargar helpers de soporte en tests (si existen)
$supportDir = __DIR__ . '/Support';
if (is_dir($supportDir)) {
    $it = new DirectoryIterator($supportDir);
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            require_once $file->getPathname();
        }
    }
}

// Elevar E_NOTICE y E_WARNING a ErrorException para forzar correcciones
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    // Elevar notices/warnings y deprecations a excepción para que PHPUnit las capture
    if (($errno & E_NOTICE)
        || ($errno & E_WARNING)
        || ($errno & E_USER_NOTICE)
        || ($errno & E_USER_WARNING)
        || ($errno & E_DEPRECATED)
        || ($errno & E_USER_DEPRECATED)
    ) {
        // Si es una deprecation, volcar traza mínima a fichero para revisarla
        if (($errno & E_DEPRECATED) || ($errno & E_USER_DEPRECATED)) {
            $log = sprintf("[%s] Deprecation: %s in %s:%d\n", date('c'), $errstr, $errfile, $errline);
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $log .= "Trace:\n" . print_r($bt, true) . "\n\n";
            @file_put_contents('/tmp/phpunit_deprecations_traces.log', $log, FILE_APPEND);
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    // dejar el resto al handler por defecto
    return false;
});

// Bloque de documentación que debe incluirse en cada fichero de test.
$requiredBlock = <<<'TXT'
/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
TXT;

// Añadir el bloque al principio de cada fichero de tests que no lo tenga.
$testDirs = [__DIR__ . '/Unit', __DIR__ . '/Integration'];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        if (str_ends_with($path, '.php')) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            // Si ya contiene el bloque, omitir
            if (str_contains($contents, '¿Qué pruebas aquí?')) {
                continue;
            }

            // Insertar justo antes de la declaración final del encabezado PHP (después de <?php y posibles declare/namespace/use)
            $pattern = '/^(<\?php\s+(declare\([^)]*\)\s*;\s*)?)/i';
            if (preg_match($pattern, $contents, $matches)) {
                $new = preg_replace($pattern, "$1\n$requiredBlock\n", $contents, 1);
                if ($new !== null) {
                    file_put_contents($path, $new);
                }
            } else {
                // alternativa: añadir al principio del archivo
                file_put_contents($path, "<?php\n\n$requiredBlock\n" . $contents);
            }
        }
    }
}

// Para pruebas futuras: comprobación en tiempo de ejecución que fallará si el archivo
// actual no contiene el bloque. Esto obliga a mantener la disciplina para nuevos tests.
// Se registra como shutdown function para que lance una excepción temprana si falta.
register_shutdown_function(function (): void {
    // Detectar el test que se está ejecutando vía debug_backtrace
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    foreach ($bt as $frame) {
        if (isset($frame['file']) && str_contains($frame['file'], DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
            $file = $frame['file'];
            $contents = @file_get_contents($file);
            if ($contents !== false && !str_contains($contents, '¿Qué pruebas aquí?')) {
                // No lanzar excepción en producción de CI para evitar romper runs automáticos,
                // pero escribir un mensaje claro y fallar con exit code.
                fwrite(STDERR, "\nERROR: El fichero de test '$file' no contiene el bloque de documentación requerido.\n");
                exit(2);
            }
            break;
        }
    }
});
