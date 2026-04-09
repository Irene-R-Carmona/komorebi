<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores con parámetros del router
 */
final class RouterParameterException extends Exception
{
    private int $httpCode = 400;

    private ?string $paramName;

    private ?string $reason;

    public function __construct(
        string $message = 'Error en parámetros de ruta',
        ?string $paramName = null,
        ?string $reason = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->paramName = $paramName;
        $this->reason = $reason;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getParamName(): ?string
    {
        return $this->paramName;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'param' => $this->paramName,
            'reason' => $this->reason,
            'code' => $this->httpCode,
        ];
    }

    public static function missing(string $paramName): self
    {
        return new self("Falta parámetro requerido: $paramName", $paramName, 'missing');
    }

    public static function invalid(string $paramName, ?string $reason = null): self
    {
        return new self("Parámetro inválido: $paramName", $paramName, $reason ?? 'invalid');
    }
}
