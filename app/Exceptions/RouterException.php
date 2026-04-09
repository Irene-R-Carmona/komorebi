<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores del router
 *
 * Proporciona contexto extra (ruta, razón) y serialización para APIs.
 */
final class RouterException extends Exception
{
    private int $httpCode = 500;

    private ?string $route;

    private ?string $reason;

    public function __construct(
        string $message = 'Error en el router',
        ?string $route = null,
        ?string $reason = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->route = $route;
        $this->reason = $reason;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'route' => $this->route,
            'reason' => $this->reason,
            'code' => $this->httpCode,
        ];
    }

    public static function routeNotFound(string $route): self
    {
        return new self("Ruta no encontrada: $route", $route, 'not_found');
    }

    public static function invalidHandler(string $route): self
    {
        return new self("Handler inválido para la ruta: $route", $route, 'invalid_handler');
    }

    public static function methodNotAllowed(string $route): self
    {
        return new self("Método no permitido para la ruta: $route", $route, 'method_not_allowed');
    }
}
