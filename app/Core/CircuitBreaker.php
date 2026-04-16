<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\CircuitOpenException;
use Throwable;

/**
 * Circuit Breaker — protege llamadas a servicios externos frente a fallos en cascada.
 *
 * Estados:
 *  - CLOSED    (normal):  las llamadas pasan; al acumular FAILURE_THRESHOLD fallos → OPEN.
 *  - OPEN      (fallo):   las llamadas se rechazan; tras TIMEOUT_SECONDS → HALF_OPEN.
 *  - HALF_OPEN (sonda):   se permite UNA llamada de prueba; éxito → CLOSED, fallo → OPEN.
 *
 * Almacenamiento:
 *  - Redis (cuando está disponible): circuit:{name}:state, circuit:{name}:failures, circuit:{name}:opened_at
 *  - In-memory estático (fallback): útil en tests unitarios o sin Redis.
 */
final class CircuitBreaker
{
    public const string STATE_CLOSED = 'CLOSED';
    public const string STATE_OPEN = 'OPEN';
    public const string STATE_HALF_OPEN = 'HALF_OPEN';

    public const int FAILURE_THRESHOLD = 5;
    public const int WINDOW_SECONDS = 60;
    public const int TIMEOUT_SECONDS = 120;

    /** @var array<string, array{state: string, failures: int, opened_at: int}> */
    private static array $memoryState = [];

    /**
     * Ejecuta la operación a través del circuit breaker.
     *
     * @throws CircuitOpenException cuando el circuito está OPEN y no ha expirado el timeout
     * @throws Throwable            cuando la propia operación lanza una excepción (el fallo se registra)
     */
    public static function call(string $name, callable $operation): mixed
    {
        $redis = Cache::getRedis();
        $currentState = self::readState($name, $redis);

        if ($currentState === self::STATE_OPEN) {
            $openedAt = self::readOpenedAt($name, $redis);
            if (\time() - $openedAt < self::TIMEOUT_SECONDS) {
                throw new CircuitOpenException("Circuito '{$name}' abierto, servicio no disponible.");
            }
            // Timeout superado: transición a HALF_OPEN para enviar una sonda
            self::writeState($name, self::STATE_HALF_OPEN, $redis);
            $currentState = self::STATE_HALF_OPEN;
        }

        try {
            $result = $operation();

            if ($currentState === self::STATE_HALF_OPEN) {
                self::close($name, $redis);
                Logger::info("[CircuitBreaker] Sonda exitosa, circuito '{$name}' cerrado.");
            } else {
                // Éxito en CLOSED: limpiar contador de fallos
                self::resetFailures($name, $redis);
            }

            return $result;
        } catch (Throwable $e) {
            self::recordFailure($name, $redis, $currentState);
            throw $e;
        }
    }

    /**
     * Resetea completamente el estado del circuito (cierra forzosamente).
     * Útil para administración y tests.
     */
    public static function reset(string $name): void
    {
        $redis = Cache::getRedis();
        if ($redis !== null) {
            $redis->del(
                "circuit:{$name}:state",
                "circuit:{$name}:failures",
                "circuit:{$name}:opened_at",
            );
        }
        unset(self::$memoryState[$name]);
    }

    /**
     * Fuerza el circuito al estado OPEN con el timestamp indicado.
     *
     * @internal Solo para uso en tests.
     */
    public static function forceOpenAt(string $name, int $openedAt): void
    {
        $redis = Cache::getRedis();
        self::writeState($name, self::STATE_OPEN, $redis);
        self::writeOpenedAt($name, $openedAt, $redis);
    }

    // ─── Lógica interna ───────────────────────────────────────────────────────

    private static function recordFailure(string $name, mixed $redis, string $previousState): void
    {
        $failures = self::incrementFailures($name, $redis);

        if ($previousState === self::STATE_HALF_OPEN || $failures >= self::FAILURE_THRESHOLD) {
            self::writeState($name, self::STATE_OPEN, $redis);
            self::writeOpenedAt($name, \time(), $redis);
            Logger::warning("[CircuitBreaker] Circuito '{$name}' abierto.", [
                'failures' => $failures,
                'previous_state' => $previousState,
            ]);
        }
    }

    private static function close(string $name, mixed $redis): void
    {
        self::writeState($name, self::STATE_CLOSED, $redis);
        self::resetFailures($name, $redis);
        self::writeOpenedAt($name, 0, $redis);
    }

    // ─── Lectura / escritura de estado ────────────────────────────────────────

    private static function readState(string $name, mixed $redis): string
    {
        if ($redis !== null) {
            $val = $redis->get("circuit:{$name}:state");

            return \is_string($val) ? $val : self::STATE_CLOSED;
        }

        return self::$memoryState[$name]['state'] ?? self::STATE_CLOSED;
    }

    private static function writeState(string $name, string $state, mixed $redis): void
    {
        if ($redis !== null) {
            $redis->set("circuit:{$name}:state", $state);
        } else {
            self::$memoryState[$name]['state'] = $state;
        }
    }

    private static function readOpenedAt(string $name, mixed $redis): int
    {
        if ($redis !== null) {
            $val = $redis->get("circuit:{$name}:opened_at");

            return \is_string($val) ? (int) $val : 0;
        }

        return self::$memoryState[$name]['opened_at'] ?? 0;
    }

    private static function writeOpenedAt(string $name, int $ts, mixed $redis): void
    {
        if ($redis !== null) {
            $redis->set("circuit:{$name}:opened_at", (string) $ts);
        } else {
            self::$memoryState[$name]['opened_at'] = $ts;
        }
    }

    private static function incrementFailures(string $name, mixed $redis): int
    {
        if ($redis !== null) {
            $key = "circuit:{$name}:failures";
            $count = (int) $redis->incr($key);
            if ($count === 1) {
                // Primera falla del ciclo: TTL = ventana de observación
                $redis->expire($key, self::WINDOW_SECONDS);
            }

            return $count;
        }

        $current = (self::$memoryState[$name]['failures'] ?? 0) + 1;
        self::$memoryState[$name]['failures'] = $current;

        return $current;
    }

    private static function resetFailures(string $name, mixed $redis): void
    {
        if ($redis !== null) {
            $redis->del("circuit:{$name}:failures");
        } else {
            self::$memoryState[$name]['failures'] = 0;
        }
    }
}
