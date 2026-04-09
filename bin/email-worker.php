#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * EmailWorker Entrypoint
 *
 * Script de inicialización para el EmailWorker especializado.
 * Procesa jobs de la cola 'emails'.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Workers\EmailWorker;

// Inicializar configuración 12-Factor
try {
    Config::init();
} catch (Throwable $e) {
    fwrite(STDERR, "[EmailWorker] [FATAL] Error de configuración: " . $e->getMessage() . "\n");
    exit(1);
}

// Iniciar worker
try {
    $worker = new EmailWorker();
    $worker->run();
} catch (Throwable $e) {
    fwrite(STDERR, "[EmailWorker] [FATAL] " . $e->getMessage() . "\n");
    exit(1);
}
