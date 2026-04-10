<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registry estático de contexto de request para logging.
 *
 * Almacena pares clave-valor en memoria para la duración de una petición.
 * RequestLogMiddleware llama a reset() al inicio y al final de cada request.
 * LogContextProcessor inyecta LogContext::all() en el campo `extra` de Monolog.
 *
 * No usa Session: este contexto es de infraestructura, no de usuario.
 * Funciona en requests HTTP, jobs de queue, workers CLI y health checks.
 */
final class LogContext
{
    /** @var array<string, mixed> */
    private static array $context = [];

    public static function set(string $key, mixed $value): void
    {
        self::$context[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$context[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$context;
    }

    public static function reset(): void
    {
        self::$context = [];
    }
}
