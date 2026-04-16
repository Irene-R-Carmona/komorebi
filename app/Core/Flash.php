<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sistema de mensajes flash (se muestran una sola vez).
 * Soporta múltiples mensajes simultáneos.
 */
final class Flash
{
    private const string KEY = '_flash_messages';

    /**
     * Asegura que la sesión esté activa.
     */
    private static function ensureSession(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }
    }

    /**
     * Añade un mensaje flash.
     */
    public static function set(string $type, string $message): void
    {
        self::ensureSession();
        $_SESSION[self::KEY][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers semánticos
    // ─────────────────────────────────────────────────────────────

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('error', $message);
    }

    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    public static function warning(string $message): void
    {
        self::set('warning', $message);
    }

    // ─────────────────────────────────────────────────────────────
    // Consumo de mensajes
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene y elimina TODOS los mensajes flash.
     * @return array<int, array{type: string, message: string}>
     */
    public static function all(): array
    {
        self::ensureSession();
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        return \is_array($messages) ? $messages : [];
    }

    /**
     * Obtiene y elimina solo UN mensaje (el primero).
     * Mantiene compatibilidad con tu código actual.
     * @return array{type: string, message: string}|null
     */
    public static function consume(): ?array
    {
        $all = self::all();

        return $all[0] ?? null;
    }

    /**
     * Obtiene y elimina el primer mensaje del tipo solicitado.
     * Devuelve el texto del mensaje o null si no existe.
     */
    public static function get(string $type): ?string
    {
        self::ensureSession();

        $messages = $_SESSION[self::KEY] ?? [];
        if (!\is_array($messages) || empty($messages)) {
            return null;
        }

        foreach ($messages as $idx => $m) {
            if (!\is_array($m)) {
                continue;
            }

            if (($m['type'] ?? '') === $type) {
                // Extraer y eliminar
                $text = $m['message'] ?? null;
                \array_splice($_SESSION[self::KEY], $idx, 1);

                return \is_string($text) ? $text : null;
            }
        }

        return null;
    }

    /**
     * Verifica si hay mensajes pendientes (sin consumirlos).
     */
    public static function has(): bool
    {
        self::ensureSession();

        return !empty($_SESSION[self::KEY]);
    }
}
