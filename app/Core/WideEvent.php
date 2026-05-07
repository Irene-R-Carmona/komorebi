<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Acumulador estático del evento canónico por request (Wide Event / Canonical Log Line).
 *
 * Construye incrementalmente el evento durante todo el ciclo de vida de una request HTTP
 * o job de queue. RequestLogMiddleware llama a reset() al inicio y emite all() al final.
 * Controllers y services llaman a set()/setSection() para enriquecer el evento con
 * contexto de negocio relevante para debugging.
 *
 * Patrón opuesto a los logs dispersos: un único evento rico por request con todos
 * los campos necesarios para debugging sin grep, con datos consultables como SQL.
 *
 * Uso:
 *   WideEvent::set('status', 201);
 *   WideEvent::setSection('reservation', ['id' => 42, 'cafe_id' => 5]);
 *   WideEvent::all();  // array completo emitido por RequestLogMiddleware
 */
final class WideEvent
{
    /** @var array<string, mixed> */
    private static array $data = [];

    /**
     * Establece un campo plano en el evento.
     * Si la clave ya existe, la sobreescribe.
     */
    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    /**
     * Establece o fusiona una sección anidada del evento.
     *
     * Si la sección ya existe, sus valores se fusionan (los nuevos prevalecen).
     * Útil para añadir contexto de negocio desde diferentes capas:
     *   WideEvent::setSection('user', ['id' => 42, 'role' => 'admin']);
     *   WideEvent::setSection('reservation', ['id' => 7, 'cafe_id' => 3]);
     *
     * @param array<string, mixed> $fields
     */
    public static function setSection(string $section, array $fields): void
    {
        if (!isset(self::$data[$section]) || !\is_array(self::$data[$section])) {
            self::$data[$section] = [];
        }

        self::$data[$section] = \array_merge(self::$data[$section], $fields);
    }

    /**
     * Devuelve el valor de una clave de primer nivel, o null si no existe.
     */
    public static function get(string $key): mixed
    {
        return self::$data[$key] ?? null;
    }

    /**
     * Indica si una clave de primer nivel existe en el evento.
     */
    public static function has(string $key): bool
    {
        return isset(self::$data[$key]);
    }

    /**
     * Devuelve el evento completo acumulado hasta este momento.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$data;
    }

    /**
     * Limpia el estado del acumulador.
     *
     * Llamado por RequestLogMiddleware al inicio y al final de cada request
     * para garantizar aislamiento entre requests concurrentes (PHP-FPM/FrankenPHP).
     */
    public static function reset(): void
    {
        self::$data = [];
    }
}
