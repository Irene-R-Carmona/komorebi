<?php

declare(strict_types=1);

namespace App\Exceptions;

use ErrorException;

/**
 * Excepción dedicada para errores de PHP convertidos desde set_error_handler
 * Extiende de la excepción nativa ErrorException para mantener compatibilidad.
 *
 * Provee utilidades adicionales como mapeo de severidad y serialización a array
 * para facilitar el logging y la respuesta en API.
 */
final class PhpErrorException extends ErrorException
{
    private const array SEVERITY_MAP = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
    ];

    /**
     * Constructor compatible con ErrorException
     *
     * @param string  $message
     * @param integer $code
     * @param integer $severity
     * @param string  $filename
     * @param integer $lineno
     */
    public function __construct(string $message = '', int $code = 0, int $severity = E_ERROR, string $filename = '', int $lineno = 0)
    {
        parent::__construct($message, $code, $severity, $filename, $lineno);
    }

    /**
     * Devuelve el nombre simbólico de la severidad (p. ej. E_ERROR)
     */
    public function getSeverityName(): string
    {
        $sev = $this->getSeverity();

        return self::SEVERITY_MAP[$sev] ?? (string) $sev;
    }

    /**
     * Serializa la excepción a un array (útil para respuestas API en modo debug y para logging)
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'severity' => $this->getSeverity(),
            'severity_name' => $this->getSeverityName(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'type' => self::class,
        ];
    }

    /**
     * Crea una instancia a partir del array retornado por error_get_last() u otro source similar.
     *
     * Espera las claves: 'message', 'type' (severity), 'file', 'line'.
     *
     * @param array<string,mixed> $error
     *
     * @return self
     */
    public static function fromErrorArray(array $error): self
    {
        $message = (string) ($error['message'] ?? 'PHP error');
        $severity = (int) ($error['type'] ?? E_ERROR);
        $file = (string) ($error['file'] ?? '');
        $line = (int) ($error['line'] ?? 0);

        return new self($message, 0, $severity, $file, $line);
    }
}
