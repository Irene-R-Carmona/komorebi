<?php

declare(strict_types=1);

namespace App\Workers;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Queue;
use App\Jobs\JobInterface;
use Throwable;

/**
 * EmailWorker - Procesa jobs de la cola 'emails'
 *
 * Worker especializado para envío de emails asíncrono.
 * Consume la cola 'emails' y ejecuta jobs de tipo SendEmailJob.
 */
final class EmailWorker
{
    private const string QUEUE_NAME = 'emails';
    private const int MAX_RETRIES = 3;
    private const int RETRY_DELAY = 60; // segundos

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
        Logger::info('[EmailWorker] Iniciado', [
            'queue' => self::QUEUE_NAME,
            'pid' => getmypid(),
            'env' => Config::getString('app.env', 'production'),
        ]);

        $this->echoToConsole('[EmailWorker] Escuchando cola de emails...');

        while (!$this->shouldStop) {
            try {
                $this->processSignals();

                if ($this->shouldStop) {
                    break;
                }

                $jobData = Queue::pop(self::QUEUE_NAME);

                if ($jobData === null) {
                    continue; // No hay trabajo, continuar esperando
                }

                $this->processJob($jobData);
            } catch (Throwable $e) {
                Logger::critical('[EmailWorker] Error en loop', [
                    'error' => $e->getMessage(),
                ]);
                sleep(5); // Evitar CPU spinning
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
            Logger::error('[EmailWorker] Job inválido', ['data' => $jobData]);

            return;
        }

        $job = new $jobClass();

        if (!$job instanceof JobInterface) {
            Logger::error('[EmailWorker] Job no implementa interfaz', [
                'class' => $jobClass,
            ]);

            return;
        }

        $start = microtime(true);
        $this->echoToConsole("[EmailWorker] Procesando: $jobClass");

        try {
            $job->handle($jobData['payload'] ?? []);
            $duration = round((microtime(true) - $start) * 1000, 2);
            $this->processed++;

            Logger::info('[EmailWorker] Job OK', [
                'job' => $jobClass,
                'ms' => $duration,
            ]);
        } catch (Throwable $e) {
            $this->errors++;
            Logger::error('[EmailWorker] Job falló', [
                'job' => $jobClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Reintentar si no se superó el límite
            $this->retryJob($jobData);
        }
    }

    /**
     * Reintenta un job fallido
     */
    private function retryJob(array $jobData): void
    {
        $attempts = ($jobData['attempts'] ?? 0) + 1;

        if ($attempts < self::MAX_RETRIES) {
            Logger::warning('[EmailWorker] Reintentando job', [
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
            Logger::error('[EmailWorker] Job descartado (máximo de reintentos)', [
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

            Logger::warning('[EmailWorker] Señal recibida, shutdown graceful', [
                'signal' => $signalName,
            ]);

            $this->echoToConsole("\n[EmailWorker] $signalName recibido. Finalizando...");
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
        Logger::info('[EmailWorker] Detenido', [
            'processed' => $this->processed,
            'errors' => $this->errors,
        ]);

        $this->echoToConsole(
            "[EmailWorker] Finalizado. Procesados: {$this->processed}, Errores: {$this->errors}"
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
