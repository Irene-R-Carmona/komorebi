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
    fwrite(STDERR, '[NotificationWorker] [FATAL] Error de configuración: ' . $e->getMessage() . "\n");
    exit(1);
}

// Error tracking (Sentry — opcional)
if (
    ($sentryDsn = (getenv('SENTRY_DSN') ?: '')) !== ''
    && function_exists('Sentry\init')
) {
    \Sentry\init([
        'dsn' => $sentryDsn,
        'environment' => getenv('APP_ENV') ?: 'production',
        'release' => getenv('APP_VERSION') ?: 'unknown',
        'enable_logs' => true,
        'send_default_pii' => false,
        'ignore_exceptions' => [
            \App\Exceptions\NotFoundException::class,
            \App\Exceptions\AuthenticationException::class,
            \App\Exceptions\AuthorizationException::class,
            \App\Exceptions\BusinessRuleException::class,
            \App\Exceptions\RateLimitException::class,
            \App\Exceptions\ValidationException::class,
        ],
    ]);

    // Vaciar buffer de eventos/logs antes de terminar (CLI requiere flush explícito)
    register_shutdown_function(static function (): void {
        \Sentry\flush();
    });
}

// Iniciar worker
try {
    $worker = new NotificationWorker();
    $worker->run();
} catch (Throwable $e) {
    fwrite(STDERR, '[NotificationWorker] [FATAL] ' . $e->getMessage() . "\n");
    exit(1);
}
