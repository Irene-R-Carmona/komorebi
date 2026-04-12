<?php

declare(strict_types=1);

namespace App\Workers;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\WideEvent;
use App\Jobs\JobInterface;
use Throwable;

/**
 * NotificationWorker - Procesa jobs de la cola 'notifications'
 *
 * Worker especializado para notificaciones (push, SMS, webhooks).
 * Consume la cola 'notifications' y ejecuta jobs de notificación.
 */
final class NotificationWorker
{
    private const string QUEUE_NAME = 'notifications';
    private const int MAX_RETRIES = 5; // Más reintentos para notificaciones
    private const int RETRY_DELAY = 30; // segundos

    private bool $shouldStop = false;
    private int $processed = 0;
    private int $errors = 0;

    public function __construct()
    {
        $this->setupSignalHandling();
    }

    /**
     * Inicia el worker (blocking loop)
     */
    public function run(): void
    {
        Logger::info('[NotificationWorker] Iniciado', [
            'queue' => self::QUEUE_NAME,
            'pid' => getmypid(),
            'env' => Config::getString('app.env', 'production'),
        ]);

        $this->echoToConsole('[NotificationWorker] Escuchando cola de notificaciones...');

        while (!$this->shouldStop) {
            try {
                $this->processSignals();

                if ($this->shouldStop) {
                    break;
                }

                $jobData = Queue::pop(self::QUEUE_NAME);

                if ($jobData === null) {
                    continue;
                }

                $this->processJob($jobData);
            } catch (Throwable $e) {
                Logger::critical('[NotificationWorker] Error en loop', [
                    'error' => $e->getMessage(),
                ]);
                sleep(5);
            }
        }

        $this->shutdown();
    }

    /**
     * Procesa un job individual
     */
    private function processJob(array $jobData): void
    {
        $jobClass = $jobData['job'] ?? null;

        if (!$jobClass || !class_exists($jobClass)) {
            Logger::error('[NotificationWorker] Job inválido', ['data' => $jobData]);

            return;
        }

        $job = new $jobClass();

        if (!$job instanceof JobInterface) {
            Logger::error('[NotificationWorker] Job no implementa interfaz', [
                'class' => $jobClass,
            ]);

            return;
        }

        $start = microtime(true);
        $this->echoToConsole("[NotificationWorker] Procesando: $jobClass");

        WideEvent::reset();
        WideEvent::set('job_class', $jobClass);
        WideEvent::set('queue', self::QUEUE_NAME);
        WideEvent::set('pid', getmypid());
        WideEvent::set('request_id', ($jobData['payload'] ?? [])['_correlation_id'] ?? '');

        try {
            $job->handle($jobData['payload'] ?? []);
            $duration = round((microtime(true) - $start) * 1000, 2);
            $this->processed++;

            WideEvent::set('duration_ms', $duration);
            WideEvent::set('outcome', 'success');
            Logger::channel('queue')->info('job.canonical', WideEvent::all());
        } catch (Throwable $e) {
            $this->errors++;
            $duration = round((microtime(true) - $start) * 1000, 2);

            WideEvent::set('duration_ms', $duration);
            WideEvent::set('outcome', 'error');
            WideEvent::setSection('error', [
                'type'    => \get_class($e),
                'message' => $e->getMessage(),
            ]);
            Logger::channel('queue')->info('job.canonical', WideEvent::all());

            Logger::error('[NotificationWorker] Job falló', [
                'job' => $jobClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->retryJob($jobData);
        } finally {
            WideEvent::reset();
        }
    }

    /**
     * Reintenta un job fallido
     */
    private function retryJob(array $jobData): void
    {
        $attempts = ($jobData['attempts'] ?? 0) + 1;

        if ($attempts < self::MAX_RETRIES) {
            Logger::warning('[NotificationWorker] Reintentando job', [
                'job' => $jobData['job'] ?? 'unknown',
                'attempt' => $attempts,
            ]);

            Queue::push(
                $jobData['job'],
                $jobData['payload'] ?? [],
                self::QUEUE_NAME,
                self::RETRY_DELAY
            );
        } else {
            Logger::error('[NotificationWorker] Job descartado (máximo de reintentos)', [
                'job' => $jobData['job'] ?? 'unknown',
                'attempts' => $attempts,
            ]);
        }
    }

    /**
     * Configura manejo de señales (graceful shutdown)
     */
    private function setupSignalHandling(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        $handler = function (int $signo): void {
            $signalName = match ($signo) {
                SIGTERM => 'SIGTERM',
                SIGINT => 'SIGINT',
                default => "Signal $signo",
            };

            Logger::warning('[NotificationWorker] Señal recibida, shutdown graceful', [
                'signal' => $signalName,
            ]);

            $this->echoToConsole("\n[NotificationWorker] $signalName recibido. Finalizando...");
            $this->shouldStop = true;
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
        pcntl_async_signals(true);
    }

    /**
     * Procesa señales pendientes
     */
    private function processSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    /**
     * Shutdown graceful
     */
    private function shutdown(): void
    {
        Logger::info('[NotificationWorker] Detenido', [
            'processed' => $this->processed,
            'errors' => $this->errors,
        ]);

        $this->echoToConsole(
            "[NotificationWorker] Finalizado. Procesados: {$this->processed}, Errores: {$this->errors}"
        );
    }

    /**
     * Output a consola (stdout)
     */
    private function echoToConsole(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
    }
}
