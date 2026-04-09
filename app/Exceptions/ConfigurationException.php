<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores de configuración del sistema
 *
 * Se lanza cuando hay problemas con la configuración de la aplicación,
 * variables de entorno faltantes o inválidas, etc.
 */
final class ConfigurationException extends Exception
{
    private int $httpCode = 500;

    private ?string $configKey;

    /**
     * @param string         $message   Mensaje del error
     * @param string|null    $configKey Clave de configuración problemática
     * @param integer        $code      Código de error interno
     * @param Throwable|null $previous  Excepción previa
     */
    public function __construct(
        string $message,
        ?string $configKey = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->configKey = $configKey;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (500)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Obtiene la clave de configuración problemática
     *
     * @return string|null
     */
    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    /**
     * Convierte la excepción a formato JSON para API
     *
     * No expone detalles de configuración por seguridad
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => 'Error de configuración del sistema',
            'code' => $this->httpCode,
            // Intencionalmente no exponemos configKey
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Configuración requerida no encontrada
     *
     * @param string $key Clave de configuración
     *
     * @return self
     */
    public static function missingKey(string $key): self
    {
        return new self(
            "Configuración requerida no encontrada: $key",
            $key
        );
    }

    /**
     * Factory method: Valor de configuración inválido
     *
     * @param string $key    Clave de configuración
     * @param string $reason Razón por la que es inválido
     *
     * @return self
     */
    public static function invalidValue(string $key, string $reason): self
    {
        return new self(
            "Valor de configuración inválido para '$key': $reason",
            $key
        );
    }

    /**
     * Factory method: Variable de entorno faltante
     *
     * @param string $envVar Nombre de la variable
     *
     * @return self
     */
    public static function missingEnvVar(string $envVar): self
    {
        return new self(
            "Variable de entorno requerida no encontrada: $envVar",
            $envVar
        );
    }

    /**
     * Factory method: Archivo de configuración no encontrado
     *
     * @param string $filePath Ruta del archivo
     *
     * @return self
     */
    public static function fileNotFound(string $filePath): self
    {
        return new self(
            "Archivo de configuración no encontrado: $filePath",
            $filePath
        );
    }

    /**
     * Factory method: Configuración de base de datos inválida
     *
     * @return self
     */
    public static function invalidDatabaseConfig(): self
    {
        return new self(
            'Configuración de base de datos inválida o incompleta',
            'database'
        );
    }

    /**
     * Factory method: Configuración de SMTP inválida
     *
     * @return self
     */
    public static function invalidSmtpConfig(): self
    {
        return new self(
            'Configuración de SMTP inválida o incompleta',
            'smtp'
        );
    }

    /**
     * Factory method: Directorio no escribible
     *
     * @param string $directory Ruta del directorio
     *
     * @return self
     */
    public static function directoryNotWritable(string $directory): self
    {
        return new self(
            "El directorio no es escribible: $directory",
            $directory
        );
    }
}
