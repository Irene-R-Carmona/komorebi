<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para límite de peticiones excedido (429)
 *
 * Se lanza cuando un cliente excede el límite de peticiones permitidas
 * en un período de tiempo determinado (rate limiting).
 */
final class RateLimitException extends Exception
{
    private int $httpCode = 429;

    private int $retryAfter;

    private int $limit;

    private ?string $action;

    /**
     * @param string         $message    Mensaje del error
     * @param integer        $retryAfter Segundos hasta poder reintentar
     * @param integer        $limit      Límite de peticiones
     * @param string|null    $action     Acción limitada
     * @param integer        $code       Código de error interno
     * @param Throwable|null $previous   Excepción previa
     */
    public function __construct(
        string $message = 'Límite de peticiones excedido',
        int $retryAfter = 60,
        int $limit = 0,
        ?string $action = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
        $this->limit = $limit;
        $this->action = $action;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (429)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Obtiene los segundos hasta poder reintentar
     *
     * @return integer Segundos
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Obtiene el límite de peticiones
     *
     * @return integer
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Obtiene la acción limitada
     *
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Convierte la excepción a formato JSON para API
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'retry_after' => $this->retryAfter,
            'limit' => $this->limit,
            'action' => $this->action,
            'code' => $this->httpCode,
        ];
    }

    /**
     * Obtiene el header Retry-After formateado
     *
     * @return string
     */
    public function getRetryAfterHeader(): string
    {
        return (string) $this->retryAfter;
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Límite para acción específica
     *
     * @param string  $action     Nombre de la acción
     * @param integer $retryAfter Segundos hasta poder reintentar
     * @param integer $limit      Límite de peticiones
     *
     * @return self
     */
    public static function forAction(string $action, int $retryAfter, int $limit): self
    {
        $minutes = \ceil($retryAfter / 60);

        return new self(
            "Límite de intentos excedido para: $action. Intenta de nuevo en $minutes minuto(s)",
            $retryAfter,
            $limit,
            $action
        );
    }

    /**
     * Factory method: Límite de login excedido
     *
     * @param integer $retryAfter Segundos hasta poder reintentar
     *
     * @return self
     */
    public static function login(int $retryAfter = 900): self
    {
        return self::forAction('login', $retryAfter, 5);
    }

    /**
     * Factory method: Límite de reset de password excedido
     *
     * @param integer $retryAfter Segundos hasta poder reintentar
     *
     * @return self
     */
    public static function passwordReset(int $retryAfter = 1800): self
    {
        return self::forAction('password_reset', $retryAfter, 3);
    }

    /**
     * Factory method: Límite de API excedido
     *
     * @param integer $retryAfter Segundos hasta poder reintentar
     * @param integer $limit      Límite de peticiones
     *
     * @return self
     */
    public static function apiCalls(int $retryAfter = 300, int $limit = 20): self
    {
        return self::forAction('api_calls', $retryAfter, $limit);
    }

    /**
     * Factory method: Límite de verificación de email excedido
     *
     * @param integer $retryAfter Segundos hasta poder reintentar
     *
     * @return self
     */
    public static function emailVerification(int $retryAfter = 600): self
    {
        return self::forAction('email_verification', $retryAfter, 5);
    }
}
