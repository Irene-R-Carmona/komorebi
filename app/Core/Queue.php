<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ExternalServiceException;
use RedisException;
use Throwable;

/**
 * Queue Manager con Redis
 *
 * Gestiona el sistema de colas asíncronas usando Redis como backend.
 * Implementa patrón Singleton para la conexión Redis.
 *
 * Uso:
 * ```php
 * Queue::push(SendEmailJob::class, ['to' => 'user@example.com']);
 * $job = Queue::pop();
 * ```
 *
 * @package App\Core
 */
final class Queue
{
    /** @var string Cola por defecto */
    private const string DEFAULT_QUEUE = 'default';

    /** @var string Prefijo para las keys de Redis */
    private const string REDIS_PREFIX = 'queue:';

    /** @var int Timeout en segundos para operaciones bloqueantes */
    private const int BLOCKING_TIMEOUT = 5;

    /** @var mixed Instancia de Redis o fallback en memoria */
    private static mixed $redis = null;

    /**
     * Constructor privado para evitar instanciación directa (Singleton)
     */
    private function __construct() {}

    /**
     * Obtiene la instancia de Redis (o fallback) para colas
     *
     * @return mixed
     * @throws ExternalServiceException Si Redis no está disponible
     */
    private static function getRedis(): mixed
    {
        if (self::$redis !== null) {
            return self::$redis;
        }

        $redis = Cache::getRedis();

        if ($redis === null) {
            throw new ExternalServiceException('Redis no está disponible para el sistema de colas. Verifica la configuración.');
        }

        self::$redis = $redis;

        return self::$redis;
    }

    /**
     * Añade un job a la cola
     *
     * @param string               $jobClass Nombre completo de la clase del job (ej: App\Jobs\SendEmailJob)
     * @param array<string, mixed> $payload  Datos para el job
     * @param string               $queue    Nombre de la cola (por defecto: 'default')
     * @param integer|null         $delay    Segundos de retraso antes de procesar (null = inmediato)
     * @return boolean True si se añadió correctamente
     * @throws ExternalServiceException Si falla la conexión con Redis
     */
    public static function push(
        string $jobClass,
        array $payload = [],
        string $queue = self::DEFAULT_QUEUE,
        ?int $delay = null
    ): bool {
        try {
            $redis = self::getRedis();

            // Preparar datos del job
            $jobData = [
                'job' => $jobClass,
                'payload' => $payload,
                'attempts' => 0,
                'created_at' => \time(),
                'available_at' => $delay !== null ? \time() + $delay : \time(),
            ];

            $serialized = \json_encode($jobData, JSON_THROW_ON_ERROR);
            $queueKey = self::REDIS_PREFIX . $queue;

            if ($delay !== null && $delay > 0) {
                // Job diferido: usar sorted set con score = timestamp de disponibilidad
                $redis->zAdd($queueKey . ':delayed', [], (float) $jobData['available_at'], $serialized);
            } else {
                // Job inmediato: usar lista
                $redis->lPush($queueKey, $serialized);
            }

            Logger::info('[Queue] Job añadido a la cola', [
                'job' => $jobClass,
                'queue' => $queue,
                'delay' => $delay,
            ]);

            return true;
        } catch (RedisException $e) {
            Logger::error('[Queue] Error al añadir job a Redis', [
                'job' => $jobClass,
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            throw new ExternalServiceException('Error al añadir job a la cola: ' . $e->getMessage());
        } catch (Throwable $e) {
            Logger::error('[Queue] Error inesperado al encolar job', [
                'job' => $jobClass,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extrae el siguiente job de la cola (operación bloqueante)
     *
     * @param string $queue Nombre de la cola
     * @return array<string, mixed>|null Datos del job o null si no hay jobs
     * @throws ExternalServiceException Si falla la conexión con Redis
     */
    public static function pop(string $queue = self::DEFAULT_QUEUE): ?array
    {
        try {
            $redis = self::getRedis();
            $queueKey = self::REDIS_PREFIX . $queue;

            // Procesar jobs diferidos primero
            self::processDelayedJobs($queue);

            // Extraer job de la cola (bloqueante para evitar polling agresivo)
            $result = $redis->brPop([$queueKey], self::BLOCKING_TIMEOUT);

            if ($result === false || !isset($result[1])) {
                return null;
            }

            $jobData = \json_decode($result[1], true, 512, JSON_THROW_ON_ERROR);

            Logger::debug('[Queue] Job extraído de la cola', [
                'job' => $jobData['job'] ?? 'unknown',
                'queue' => $queue,
                'attempts' => $jobData['attempts'] ?? 0,
            ]);

            return $jobData;
        } catch (RedisException $e) {
            Logger::error('[Queue] Error al extraer job de Redis', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            throw new ExternalServiceException('Error al extraer job de la cola: ' . $e->getMessage());
        } catch (Throwable $e) {
            Logger::error('[Queue] Error inesperado al extraer job', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mueve jobs diferidos disponibles a la cola principal
     *
     * @param string $queue Nombre de la cola
     * @return void
     */
    private static function processDelayedJobs(string $queue): void
    {
        try {
            $redis = self::getRedis();
            $delayedKey = self::REDIS_PREFIX . $queue . ':delayed';
            $queueKey = self::REDIS_PREFIX . $queue;
            $now = \time();

            // Obtener jobs cuyo timestamp de disponibilidad ya pasó
            $jobs = $redis->zRangeByScore($delayedKey, '-inf', (string) $now);

            if (empty($jobs)) {
                return;
            }

            foreach ($jobs as $job) {
                // Mover a cola principal
                $redis->lPush($queueKey, $job);
                // Eliminar de cola diferida
                $redis->zRem($delayedKey, $job);
            }

            Logger::debug('[Queue] Jobs diferidos procesados', [
                'queue' => $queue,
                'count' => \count($jobs),
            ]);
        } catch (Throwable $e) {
            Logger::error('[Queue] Error al procesar jobs diferidos', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reencola un job que falló para reintentar
     *
     * @param array<string, mixed> $jobData     Datos del job
     * @param string               $queue       Nombre de la cola
     * @param integer              $maxAttempts Número máximo de reintentos (por defecto: 3)
     * @return boolean True si se reencoló, false si alcanzó máximo de intentos
     */
    public static function retry(
        array $jobData,
        string $queue = self::DEFAULT_QUEUE,
        int $maxAttempts = 3
    ): bool {
        $attempts = ($jobData['attempts'] ?? 0) + 1;

        if ($attempts >= $maxAttempts) {
            Logger::warning('[Queue] Job alcanzó máximo de reintentos', [
                'job' => $jobData['job'] ?? 'unknown',
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
            ]);

            // Mover a cola de fallos permanentes
            self::pushToFailedQueue($jobData, $queue);

            return false;
        }

        try {
            $redis = self::getRedis();
            $jobData['attempts'] = $attempts;

            // Delay exponencial: 2^attempts segundos
            $delay = 2 ** $attempts;

            $serialized = \json_encode($jobData, JSON_THROW_ON_ERROR);
            $delayedKey = self::REDIS_PREFIX . $queue . ':delayed';
            $availableAt = \time() + $delay;

            $redis->zAdd($delayedKey, [], (float) $availableAt, $serialized);

            Logger::info('[Queue] Job reencolado para reintento', [
                'job' => $jobData['job'] ?? 'unknown',
                'attempts' => $attempts,
                'delay' => $delay,
            ]);

            return true;
        } catch (Throwable $e) {
            Logger::error('[Queue] Error al reencolar job', [
                'job' => $jobData['job'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mueve un job a la cola de fallos permanentes
     *
     * @param array<string, mixed> $jobData Datos del job
     * @param string               $queue   Nombre de la cola original
     * @return void
     */
    private static function pushToFailedQueue(array $jobData, string $queue): void
    {
        try {
            $redis = self::getRedis();
            $failedKey = self::REDIS_PREFIX . 'failed';

            $failedData = [
                'job' => $jobData,
                'queue' => $queue,
                'failed_at' => \time(),
            ];

            $serialized = \json_encode($failedData, JSON_THROW_ON_ERROR);
            $redis->lPush($failedKey, $serialized);

            Logger::error('[Queue] Job movido a cola de fallos', [
                'job' => $jobData['job'] ?? 'unknown',
                'queue' => $queue,
            ]);
        } catch (Throwable $e) {
            Logger::critical('[Queue] Error al guardar job fallido', [
                'job' => $jobData['job'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtiene el tamaño de una cola
     *
     * @param string $queue Nombre de la cola
     * @return integer Número de jobs en la cola
     */
    public static function size(string $queue = self::DEFAULT_QUEUE): int
    {
        try {
            $redis = self::getRedis();
            $queueKey = self::REDIS_PREFIX . $queue;

            return (int) $redis->lLen($queueKey);
        } catch (Throwable $e) {
            Logger::error('[Queue] Error al obtener tamaño de cola', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Limpia una cola (elimina todos los jobs)
     *
     * @param string $queue Nombre de la cola
     * @return boolean True si se limpió correctamente
     */
    public static function clear(string $queue = self::DEFAULT_QUEUE): bool
    {
        try {
            $redis = self::getRedis();
            $queueKey = self::REDIS_PREFIX . $queue;
            $delayedKey = $queueKey . ':delayed';

            $redis->del($queueKey);
            $redis->del($delayedKey);

            Logger::warning('[Queue] Cola limpiada', ['queue' => $queue]);

            return true;
        } catch (Throwable $e) {
            Logger::error('[Queue] Error al limpiar cola', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
