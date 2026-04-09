#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * NotificationWorker Entrypoint
 *
 * Script de inicialización para el NotificationWorker especializado.
 * Procesa jobs de la cola 'notifications'.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Workers\NotificationWorker;

// Inicializar configuración 12-Factor
try {
    Config::init();
} catch (Throwable $e) {
    fwrite(STDERR, "[NotificationWorker] [FATAL] Error de configuración: " . $e->getMessage() . "\n");
    exit(1);
}

// Iniciar worker
try {
    $worker = new NotificationWorker();
    $worker->run();
} catch (Throwable $e) {
    fwrite(STDERR, "[NotificationWorker] [FATAL] " . $e->getMessage() . "\n");
    exit(1);
}
