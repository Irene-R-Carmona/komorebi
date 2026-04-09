<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores del sistema de ficheros (IO)
 *
 * Encapsula errores al crear/eliminar/escribir en disco sin exponer detalles
 * sensibles en producción.
 */
final class FilesystemException extends Exception
{
    private int $httpCode = 500;

    private ?string $path;

    public function __construct(string $message = 'Error en el sistema de ficheros', ?string $path = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->path = $path;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Factory: error al crear directorio
     */
    public static function directoryCreationFailed(string $path): self
    {
        return new self("No se pudo crear el directorio: $path", $path);
    }

    /**
     * Factory helper para crear la excepción con un mensaje simple.
     */
    public static function withMessage(string $message, ?string $path = null): self
    {
        return new self($message, $path);
    }
}
