<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Utilidad para gestionar variables de entorno (.env).
 *
 * Prioridad: getenv() → $_ENV → default
 * Compatible con Docker y configuraciones PHP variadas.
 */
final class Env
{
    /**
     * Cache interno para evitar llamadas repetidas a getenv().
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * Obtiene una variable de entorno.
     *
     * Prioridad: `getenv()` → `$_ENV` → `$default`.
     * Almacena el resultado en caché interno para llamadas posteriores.
     *
     * @param string $key     Nombre de la variable de entorno
     * @param string $default Valor por defecto si no existe
     * @return string Valor de la variable (o `$default`)
     */
    public static function get(string $key, string $default = ''): string
    {
        // Comprobar caché primero
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $val = \getenv($key);
        if ($val !== false && $val !== '') {
            self::$cache[$key] = (string) $val;

            return self::$cache[$key];
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            self::$cache[$key] = (string) $_ENV[$key];

            return self::$cache[$key];
        }

        return $default;
    }

    /**
     * Obtiene una variable de entorno como entero.
     *
     * @param string  $key     Nombre de la variable
     * @param integer $default Valor por defecto
     * @return integer
     */
    public static function int(string $key, int $default = 0): int
    {
        $val = self::get($key);

        return $val !== '' ? (int) $val : $default;
    }

    /**
     * Obtiene una variable de entorno como booleano.
     * Acepta '1','true','yes','on' (case-insensitive) como verdaderos.
     *
     * @param string  $key     Nombre de la variable
     * @param boolean $default Valor por defecto
     * @return boolean
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === '') {
            return $default;
        }

        return \in_array(\strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Verifica que una variable exista y no esté vacía.
     *
     * Lanza una excepción si la variable no está presente — útil para
     * validar configuración crítica en arranque de la aplicación.
     *
     * @param string $key Nombre de la variable requerida
     * @throws RuntimeException Si la variable no está definida o está vacía
     * @return string Valor de la variable
     */
    public static function require(string $key): string
    {
        $val = self::get($key);
        if ($val === '') {
            throw new \RuntimeException("Variable de entorno requerida: $key");
        }

        return $val;
    }

    /**
     * Limpia la caché interna de variables (útil para testing).
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
