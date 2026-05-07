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
 * AbstractWorker — Base para todos los queue workers del sistema.
 *
 * Encapsula el loop de procesamiento, señales POSIX, heartbeat y reintentos.
 * Las subclases sólo definen sus constantes de configuración mediante los
 * métodos abstractos: getQueueName, getMaxRetries, getRetryDelay,
 * getHeartbeatFilePath y getWorkerName.
 */
abstract class AbstractWorker
{
    private const int HEARTBEAT_INTERVAL = 60;

    private bool $shouldStop = false;
    private int $processed = 0;
    private int $errors = 0;
    private float $lastHeartbeat = 0.0;
    private float $startTime = 0.0;

    abstract protected function getQueueName(): string;
    abstract protected function getMaxRetries(): int;
    abstract protected function getRetryDelay(): int;
    abstract protected function getHeartbeatFilePath(): string;
    abstract protected function getWorkerName(): string;

    public function __construct()
    {
        $this->setupSignalHandling();
    }

    /**
     * Inicia el worker (blocking loop).
     */
    public function run(): void
    {
        $workerName = $this->getWorkerName();
        $queueName = $this->getQueueName();

        Logger::info("[$workerName] Iniciado", [
            'queue' => $queueName,
            'pid' => \getmypid(),
            'env' => Config::getString('app.env', 'production'),
        ]);

        try {
            Queue::ensureConsumerGroup($queueName);
        } catch (Throwable $e) {
            Logger::warning("[$workerName] No se pudo crear consumer group", ['error' => $e->getMessage()]);
        }

        $this->echoToConsole("[$workerName] Escuchando cola '$queueName'...");

        $this->startTime = \microtime(true);
        $this->lastHeartbeat = \microtime(true);

        while (!$this->shouldStop) {
            try {
                $this->processSignals();

                if ($this->shouldStop) {
                    break;
                }

                $this->emitHeartbeatIfDue();

                $jobData = Queue::pop($queueName);

                if ($jobData === null) {
                    continue;
                }

                $this->processJob($jobData);
            } catch (Throwable $e) {
                Logger::critical("[$workerName] Error en loop", ['error' => $e->getMessage()]);
                \sleep(5);
            }
        }

        $this->shutdown();
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function emitHeartbeatIfDue(): void
    {
        $now = \microtime(true);

        if (($now - $this->lastHeartbeat) < self::HEARTBEAT_INTERVAL) {
            return;
        }

        $this->lastHeartbeat = $now;

        \file_put_contents($this->getHeartbeatFilePath(), (string) (int) $now);

        Logger::info("[{$this->getWorkerName()}] Heartbeat", [
            'queue' => $this->getQueueName(),
            'pending' => Queue::size($this->getQueueName()),
            'processed' => $this->processed,
            'errors' => $this->errors,
            'uptime_s' => (int) ($now - $this->startTime),
            'pid' => \getmypid(),
        ]);
    }

    private function processJob(array $jobData): void
    {
        $workerName = $this->getWorkerName();
        $queueName = $this->getQueueName();
        $jobClass = $jobData['job'] ?? null;

        if (!$jobClass || !\class_exists($jobClass)) {
            Logger::error("[$workerName] Job inválido", ['data' => $jobData]);

            return;
        }

        $job = new $jobClass();

        if (!$job instanceof JobInterface) {
            Logger::error("[$workerName] Job no implementa interfaz", ['class' => $jobClass]);

            return;
        }

        $start = \microtime(true);
        $this->echoToConsole("[$workerName] Procesando: $jobClass");

        WideEvent::reset();
        WideEvent::set('job_class', $jobClass);
        WideEvent::set('queue', $queueName);
        WideEvent::set('pid', \getmypid());
        WideEvent::set('request_id', ($jobData['payload'] ?? [])['_correlation_id'] ?? '');

        try {
            $job->handle($jobData['payload'] ?? []);
            $duration = \round((\microtime(true) - $start) * 1000, 2);
            $this->processed++;

            WideEvent::set('duration_ms', $duration);
            WideEvent::set('outcome', 'success');
            Logger::channel('queue')->info('job.canonical', WideEvent::all());

            $streamId = $jobData['_stream_id'] ?? null;
            if ($streamId !== null) {
                Queue::acknowledge($queueName, $streamId);
            }
        } catch (Throwable $e) {
            $this->errors++;
            $duration = \round((\microtime(true) - $start) * 1000, 2);

            WideEvent::set('duration_ms', $duration);
            WideEvent::set('outcome', 'error');
            WideEvent::setSection('error', [
                'type' => \get_class($e),
                'message' => $e->getMessage(),
            ]);
            Logger::channel('queue')->info('job.canonical', WideEvent::all());

            Logger::error("[$workerName] Job falló", [
                'job' => $jobClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->retryJob($jobData);

            $streamId = $jobData['_stream_id'] ?? null;
            if ($streamId !== null) {
                Queue::acknowledge($queueName, $streamId);
            }
        } finally {
            WideEvent::reset();
        }
    }

    private function retryJob(array $jobData): void
    {
        $workerName = $this->getWorkerName();
        $queueName = $this->getQueueName();
        $attempts = ($jobData['attempts'] ?? 0) + 1;

        if ($attempts < $this->getMaxRetries()) {
            Logger::warning("[$workerName] Reintentando job", [
                'job' => $jobData['job'] ?? 'unknown',
                'attempt' => $attempts,
            ]);

            Queue::push(
                $jobData['job'],
                \array_merge($jobData['payload'] ?? [], [
                    '_correlation_id' => ($jobData['payload']['_correlation_id'] ?? ''),
                ]),
                $queueName,
                $this->getRetryDelay()
            );
        } else {
            Logger::error("[$workerName] Job descartado (máximo de reintentos)", [
                'job' => $jobData['job'] ?? 'unknown',
                'attempts' => $attempts,
            ]);
        }
    }

    private function setupSignalHandling(): void
    {
        if (!\function_exists('pcntl_signal')) {
            return;
        }

        $handler = function (int $signo): void {
            $signalName = match ($signo) {
                SIGTERM => 'SIGTERM',
                SIGINT => 'SIGINT',
                default => "Signal $signo",
            };

            $workerName = $this->getWorkerName();
            Logger::warning("[$workerName] Señal recibida, shutdown graceful", [
                'signal' => $signalName,
            ]);

            $this->echoToConsole("\n[$workerName] $signalName recibido. Finalizando...");
            $this->shouldStop = true;
        };

        \pcntl_signal(SIGTERM, $handler);
        \pcntl_signal(SIGINT, $handler);
        \pcntl_async_signals(true);
    }

    private function processSignals(): void
    {
        if (\function_exists('pcntl_signal_dispatch')) {
            \pcntl_signal_dispatch();
        }
    }

    private function shutdown(): void
    {
        $workerName = $this->getWorkerName();

        Logger::info("[$workerName] Detenido", [
            'processed' => $this->processed,
            'errors' => $this->errors,
        ]);

        $this->echoToConsole(
            "[$workerName] Finalizado. Procesados: {$this->processed}, Errores: {$this->errors}"
        );
    }

    private function echoToConsole(string $message): void
    {
        \fwrite(STDOUT, $message . "\n");
    }
}
