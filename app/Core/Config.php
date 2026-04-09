<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Configuración centralizada 12-Factor.
 *
 * Principios aplicados:
 * - Config separada del código (variables de entorno)
 * - Fail-fast si falta configuración crítica
 * - No dependencia de archivos .env (solo getenv/$_ENV)
 */
final class Config
{
    private static array $cache = [];
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Cargar desde variables de entorno (12-Factor III)
        self::$cache = [
            'app' => [
                'env' => self::env('APP_ENV', 'production'),
                'debug' => self::bool('APP_DEBUG', false),
                'url' => self::env('APP_URL', 'http://localhost'),
                'timezone' => self::env('APP_TIMEZONE', 'Europe/Madrid'),
                // Secret crítico - usa SecretLoader
                'key' => SecretLoader::require('app_key'),
            ],

            'database' => [
                'host' => self::env('DB_HOST', 'localhost'),
                'port' => self::int('DB_PORT', 3306),
                'database' => self::env('DB_DATABASE', 'komorebi'),
                'username' => self::env('DB_USERNAME', 'root'),
                // Secret sensible
                'password' => SecretLoader::require('db_password'),
                'charset' => 'utf8mb4',
            ],

            'cache' => [
                'driver' => self::env('CACHE_DRIVER', 'redis'),
                'redis' => [
                    'host' => self::env('REDIS_HOST', 'localhost'),
                    'port' => self::int('REDIS_PORT', 6379),
                    'password' => SecretLoader::get('redis_password'),
                ],
            ],

            'session' => [
                'driver' => self::env('SESSION_DRIVER', 'file'),
                'lifetime' => self::int('SESSION_LIFETIME', 120),
                'secret' => SecretLoader::get('app_key'),
            ],

            'logging' => [
                'channel' => self::env('LOG_CHANNEL', 'stderr'),
                'level' => self::env('LOG_LEVEL', 'info'),
            ],
        ];

        self::$initialized = true; // Marcar inicializado ANTES de validar para evitar bucle infinito
        self::validateCritical();
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$initialized) {
            self::init();
        }

        $keys = explode('.', $key);
        $value = self::$cache;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function require(string $key): mixed
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Configuración requerida ausente: $key");
        }

        return $value;
    }

    /**
     * Validación fail-fast (12-Factor)
     */
    private static function validateCritical(): void
    {
        $required = [
            'app.key' => 'APP_KEY requerido (mínimo 32 caracteres)',
            'database.database' => 'DB_DATABASE requerido',
            'database.username' => 'DB_USERNAME requerido',
        ];

        foreach ($required as $key => $message) {
            if (empty(self::get($key))) {
                throw new RuntimeException("ERROR CRÍTICO: $message");
            }
        }

        // Validación especial para APP_KEY en producción
        if (self::get('app.env') === 'production') {
            $key = self::get('app.key');
            if (strlen($key) < 32) {
                throw new RuntimeException('APP_KEY debe tener al menos 32 caracteres en producción');
            }
        }
    }

    // Helpers privados
    /**
     * @return null|scalar|string[]
     *
     * @psalm-return non-empty-list<string>|null|scalar
     */
    private static function env(string $key, ?string $default = null): array|float|bool|int|string|null
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    private static function bool(string $key, bool $default): bool
    {
        $val = self::env($key);
        return $val === null ? $default : filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    private static function int(string $key, int $default): int
    {
        return (int) self::env($key, (string)$default);
    }

    // Tipos seguros de acceso a configuración
    public static function getInt(string $key, int $default): int
    {
        $val = self::get($key);
        if (is_int($val)) {
            return $val;
        }
        if (is_string($val) && is_numeric($val)) {
            return (int) $val;
        }
        return $default;
    }

    public static function getBool(string $key, bool $default): bool
    {
        $val = self::get($key);
        if (is_bool($val)) {
            return $val;
        }
        if (is_string($val)) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }
        return $default;
    }

    public static function getString(string $key, string $default): string
    {
        $val = self::get($key);
        if (is_string($val)) {
            return $val;
        }
        if (is_scalar($val)) {
            return (string) $val;
        }
        return $default;
    }
}
