<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores de autorización (403)
 *
 * Se lanza cuando un usuario autenticado no tiene permisos
 * para realizar una acción o acceder a un recurso.
 */
final class AuthorizationException extends Exception
{
    private int $httpCode = 403;

    private ?string $permission;

    private ?string $resource;

    /**
     * @param string         $message    Mensaje del error
     * @param string|null    $permission Permiso requerido
     * @param string|null    $resource   Recurso al que se intenta acceder
     * @param integer        $code       Código de error interno
     * @param Throwable|null $previous   Excepción previa
     */
    public function __construct(
        string $message = 'No autorizado',
        ?string $permission = null,
        ?string $resource = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->permission = $permission;
        $this->resource = $resource;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (403)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Obtiene el permiso requerido
     *
     * @return string|null
     */
    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * Obtiene el recurso protegido
     *
     * @return string|null
     */
    public function getResource(): ?string
    {
        return $this->resource;
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
            'permission' => $this->permission,
            'resource' => $this->resource,
            'code' => $this->httpCode,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Falta permiso específico
     *
     * @param string $permission Nombre del permiso
     *
     * @return self
     */
    public static function missingPermission(string $permission): self
    {
        return new self(
            "No tienes permiso para: $permission",
            $permission
        );
    }

    /**
     * Factory method: Falta rol específico
     *
     * @param string $role Nombre del rol
     *
     * @return self
     */
    public static function missingRole(string $role): self
    {
        return new self(
            "Se requiere el rol: $role",
            "role:$role"
        );
    }

    /**
     * Factory method: No tiene acceso al recurso
     *
     * @param string $resource Tipo de recurso
     *
     * @return self
     */
    public static function resourceAccess(string $resource): self
    {
        return new self(
            'No tienes acceso a este recurso',
            null,
            $resource
        );
    }

    /**
     * Factory method: Acceso denegado genérico
     *
     * @return self
     */
    public static function accessDenied(): self
    {
        return new self('Acceso denegado');
    }

    /**
     * Factory method: No puede modificar este recurso
     *
     * @param string $resource Tipo de recurso
     *
     * @return self
     */
    public static function cannotModify(string $resource): self
    {
        return new self(
            "No puedes modificar este $resource",
            "modify:$resource",
            $resource
        );
    }

    /**
     * Factory method: No puede eliminar este recurso
     *
     * @param string $resource Tipo de recurso
     *
     * @return self
     */
    public static function cannotDelete(string $resource): self
    {
        return new self(
            "No puedes eliminar este $resource",
            "delete:$resource",
            $resource
        );
    }
}
