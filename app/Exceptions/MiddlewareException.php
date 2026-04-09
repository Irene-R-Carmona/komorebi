<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores en middlewares
 *
 * Proporciona información adicional (nombre del middleware, razón) y
 * un método toArray() para serializar la excepción en respuestas API.
 */
final class MiddlewareException extends Exception
{
    private int $httpCode = 500;

    private ?string $middleware;

    private ?string $reason;

    public function __construct(
        string $message = 'Error en middleware',
        ?string $middleware = null,
        ?string $reason = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->middleware = $middleware;
        $this->reason = $reason;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getMiddleware(): ?string
    {
        return $this->middleware;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'middleware' => $this->middleware,
            'reason' => $this->reason,
            'code' => $this->httpCode,
        ];
    }

    // Factory methods

    public static function missing(string $middleware): self
    {
        return new self("Middleware requerido no encontrado: $middleware", $middleware);
    }

    public static function notCallable(string $middleware): self
    {
        return new self("Middleware '$middleware' no es invocable", $middleware);
    }

    public static function shortCircuit(string $middleware, ?string $reason = null): self
    {
        return new self("Middleware interrumpió la ejecución: $middleware", $middleware, $reason);
    }
}
