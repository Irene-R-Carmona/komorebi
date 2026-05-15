#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Worker PHP 12-Factor para procesamiento de colas asíncronas.
 *
 * Cambios 12-Factor:
 * - No carga archivos .env (usa variables de entorno inyectadas)
 * - Logs a stderr/stdout (no archivos)
 * - Config vía Config::init() (12-Factor III)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\WideEvent;
use App\Jobs\JobInterface;

// ============================================================================
// INICIALIZACIÓN 12-FACTOR
// ============================================================================

// Cargar configuración desde variables de entorno (no archivos)
try {
    Config::init();
} catch (Throwable $e) {
    fwrite(STDERR, '[Worker] [FATAL] Error de configuración: ' . $e->getMessage() . "\n");
    exit(1);
}

// Error tracking (Sentry — opcional)
if (
    ($sentryDsn = Env::get('SENTRY_DSN', '')) !== ''
    && \str_starts_with($sentryDsn, 'https://')
    && function_exists('Sentry\init')
) {
    try {
    \Sentry\init([
        'dsn' => $sentryDsn,
        'environment' => Env::get('APP_ENV', 'production'),
        'release' => Env::get('APP_VERSION', 'unknown'),
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
    } catch (Throwable $e) {
        fwrite(STDERR, '[Sentry] Error al inicializar: ' . $e->getMessage() . "\n");
    }
}

// Obtener nombre de la cola
$queueName = $argv[1] ?? 'default';

// Validar conexión a servicios
try {
    $testSize = Queue::size($queueName);
    Logger::info('[Worker] Iniciado', [
        'queue' => $queueName,
        'pending' => $testSize,
        'pid' => getmypid(),
        'env' => Config::getString('app.env', 'production'),
    ]);
} catch (Throwable $e) {
    Logger::critical('[Worker] Sin conexión a Redis', ['error' => $e->getMessage()]);
    fwrite(STDERR, "[Worker] ERROR: No se pudo conectar a Redis\n");
    exit(1);
}

// Crear consumer group en el stream (XGROUP CREATE MKSTREAM)
try {
    Queue::ensureConsumerGroup($queueName);
} catch (Throwable $e) {
    Logger::warning('[Worker] No se pudo crear consumer group', ['error' => $e->getMessage()]);
}

// ============================================================================
// SIGNAL HANDLING (Factor IX: Disposability)
// ============================================================================

$shouldStop = false;

$signalHandler = static function (int $signo) use (&$shouldStop, $queueName): void {
    $signalName = match ($signo) {
        SIGTERM => 'SIGTERM',
        SIGINT => 'SIGINT',
        default => "Signal $signo",
    };

    Logger::warning('[Worker] Señal recibida, shutdown graceful', [
        'signal' => $signalName,
        'queue' => $queueName,
    ]);

    fwrite(STDOUT, "\n[Worker] $signalName recibido. Finalizando...\n");
    $shouldStop = true;
};

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
    pcntl_async_signals(true);
}

// ============================================================================
// LOOP PRINCIPAL
// ============================================================================

fwrite(STDOUT, "[Worker] Escuchando cola '$queueName'...\n");

$processed = 0;
$errors = 0;

while (!$shouldStop) {
    try {
        // Procesar señales
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        if ($shouldStop) {
            break;
        }

        // Obtener job (bloqueante con timeout)
        $jobData = Queue::pop($queueName);

        if ($jobData === null) {
            continue; // No hay trabajo, volver a esperar
        }

        $jobClass = $jobData['job'] ?? null;

        if (!$jobClass || !class_exists($jobClass)) {
            Logger::error('[Worker] Job inválido', ['data' => $jobData]);
            continue;
        }

        $job = new $jobClass();

        if (!$job instanceof JobInterface) {
            Logger::error('[Worker] Job no implementa interfaz', ['class' => $jobClass]);
            continue;
        }

        // Ejecutar
        $start = microtime(true);
        fwrite(STDOUT, "[Worker] Procesando: $jobClass\n");

        WideEvent::reset();
        WideEvent::set('job_class', $jobClass);
        WideEvent::set('queue', $queueName);
        WideEvent::set('pid', getmypid());
        WideEvent::set('request_id', $jobData['correlation_id'] ?? '');

        try {
            $job->handle($jobData['payload'] ?? []);
            $duration = round((microtime(true) - $start) * 1000, 2);
            $processed++;

            WideEvent::set('duration_ms', $duration);
            WideEvent::set('outcome', 'success');
            Logger::channel('queue')->info('job.canonical', WideEvent::all());

            // XACK: confirmar procesamiento exitoso (elimina de PEL)
            $streamId = $jobData['_stream_id'] ?? null;
            if ($streamId !== null) {
                Queue::acknowledge($queueName, $streamId);
            }
        } catch (Throwable $e) {
            $errors++;
            $duration = round((microtime(true) - $start) * 1000, 2);

            WideEvent::set('duration_ms', $duration);
            WideEvent::set('outcome', 'error');
            WideEvent::setSection('error', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            Logger::channel('queue')->info('job.canonical', WideEvent::all());

            Logger::error('[Worker] Job falló', [
                'job' => $jobClass,
                'error' => $e->getMessage(),
            ]);

            // Reintentar si aplica (empuja nuevo mensaje al stream/delayed)
            Queue::retry($jobData, $queueName, 3);

            // XACK: eliminar original del PEL (ya encolado nuevo mensaje para retry)
            $streamId = $jobData['_stream_id'] ?? null;
            if ($streamId !== null) {
                Queue::acknowledge($queueName, $streamId);
            }
        } finally {
            WideEvent::reset();
        }
    } catch (Throwable $e) {
        Logger::critical('[Worker] Error en loop', ['error' => $e->getMessage()]);
        sleep(5); // Evitar CPU spinning en errores críticos
    }
}

// ============================================================================
// SHUTDOWN
// ============================================================================

Logger::info('[Worker] Detenido', [
    'processed' => $processed,
    'errors' => $errors,
]);

fwrite(STDOUT, "[Worker] Finalizado. Procesados: $processed, Errores: $errors\n");
exit(0);
