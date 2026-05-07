<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Loader de secrets compatible con 12-Factor.
 *
 * Prioridad:
 * 1. Variables de entorno (desarrollo con Docker Compose)
 * 2. Archivos en /run/secrets/ (simulación de Docker Swarm/producción)
 */
final class SecretLoader
{
    /**
     * Obtiene un secret desde variables de entorno o archivo simulado.
     *
     * @param string $name Nombre del secret (ej: db_password)
     * @param string|null $default Valor por defecto (solo desarrollo)
     *
     * @return null|scalar|string[]
     *
     * @psalm-return non-empty-list<string>|null|scalar
     */
    public static function get(string $name, ?string $default = null)
    {
        // 1. Intentar variable de entorno (método estándar 12-Factor)
        $envName = \strtoupper($name);
        $value = $_ENV[$envName] ?? $_SERVER[$envName] ?? \getenv($envName);

        if (!empty($value)) {
            return $value;
        }

        // 2. Simulación Docker Secrets (para demos de producción)
        $secretPath = "/run/secrets/$name";
        if (\file_exists($secretPath) && \is_readable($secretPath)) {
            $content = \trim(\file_get_contents($secretPath));
            if (!empty($content)) {
                return $content;
            }
        }

        // 3. Fallback solo para desarrollo local
        if ($default !== null) {
            // Advertencia en logs si usa default en "producción"
            if (self::isProduction()) {
                Logger::warning('Usando default para secret en producción', ['secret' => $name]);
            }

            return $default;
        }

        return null;
    }

    /**
     * Versión estricta - lanza excepción si no existe
     *
     * @return scalar|string[]
     *
     * @psalm-return non-empty-list<string>|scalar
     */
    public static function require(string $name)
    {
        $value = self::get($name);

        if (empty($value)) {
            throw new RuntimeException(
                "Secret requerido no encontrado: $name. " .
                    'Configura la variable de entorno ' . \strtoupper($name) . ' ' .
                    "o monta el archivo en /run/secrets/$name"
            );
        }

        return $value;
    }

    private static function isProduction(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }
}
