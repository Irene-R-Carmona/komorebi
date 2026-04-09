<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores de autenticación (401)
 *
 * Se lanza cuando hay problemas con la autenticación del usuario.
 */
final class AuthenticationException extends Exception
{
    private int $httpCode = 401;

    private ?string $reason;

    /**
     * @param string         $message  Mensaje del error
     * @param string|null    $reason   Razón específica del fallo
     * @param integer        $code     Código de error interno
     * @param Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $message = 'No autenticado',
        ?string $reason = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->reason = $reason;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (401)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Obtiene la razón del fallo de autenticación
     *
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
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
            'reason' => $this->reason,
            'code' => $this->httpCode,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Credenciales inválidas
     *
     * @return self
     */
    public static function invalidCredentials(): self
    {
        return new self(
            'Credenciales inválidas',
            'invalid_credentials'
        );
    }

    /**
     * Factory method: Token expirado
     *
     * @return self
     */
    public static function tokenExpired(): self
    {
        return new self(
            'Token expirado',
            'token_expired'
        );
    }

    /**
     * Factory method: Sesión expirada
     *
     * @return self
     */
    public static function sessionExpired(): self
    {
        return new self(
            'Sesión expirada',
            'session_expired'
        );
    }

    /**
     * Factory method: Cuenta inactiva
     *
     * @return self
     */
    public static function accountInactive(): self
    {
        return new self(
            'Tu cuenta está inactiva. Contacta con el administrador.',
            'account_inactive'
        );
    }

    /**
     * Factory method: Email no verificado
     *
     * @return self
     */
    public static function emailNotVerified(): self
    {
        return new self(
            'Debes verificar tu email antes de continuar',
            'email_not_verified'
        );
    }

    /**
     * Factory method: Usuario no autenticado
     *
     * @return self
     */
    public static function notAuthenticated(): self
    {
        return new self(
            'Debes iniciar sesión para acceder a este recurso',
            'not_authenticated'
        );
    }
}
