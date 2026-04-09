<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para recursos no encontrados (404)
 *
 * Se lanza cuando un recurso solicitado no existe en el sistema.
 */
final class NotFoundException extends Exception
{
    private ?string $resourceType;

    private mixed $resourceId;

    private int $httpCode = 404;

    /**
     * @param string         $message      Mensaje del error
     * @param string|null    $resourceType Tipo de recurso (Café, Usuario, etc.)
     * @param mixed          $resourceId   ID del recurso no encontrado
     * @param integer        $code         Código de error interno
     * @param Throwable|null $previous     Excepción previa
     */
    public function __construct(
        string $message = 'Recurso no encontrado',
        ?string $resourceType = null,
        mixed $resourceId = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
    }

    /**
     * Obtiene el tipo de recurso no encontrado
     *
     * @return string|null
     */
    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    /**
     * Obtiene el ID del recurso no encontrado
     *
     * @return mixed
     */
    public function getResourceId(): mixed
    {
        return $this->resourceId;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (404)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
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
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'code' => $this->httpCode,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Crea excepción para un recurso genérico
     *
     * @param string $resourceType Tipo de recurso
     * @param mixed  $resourceId   ID del recurso
     *
     * @return self
     */
    public static function forResource(string $resourceType, mixed $resourceId): self
    {
        return new self(
            "$resourceType no encontrado",
            $resourceType,
            $resourceId
        );
    }

    /**
     * Factory method: Café no encontrado
     *
     * @param integer $id ID del café
     *
     * @return self
     */
    public static function cafe(int $id): self
    {
        return self::forResource('Café', $id);
    }

    /**
     * Factory method: Reserva no encontrada
     *
     * @param integer $id ID de la reserva
     *
     * @return self
     */
    public static function reservation(int $id): self
    {
        return self::forResource('Reserva', $id);
    }

    /**
     * Factory method: Producto no encontrado
     *
     * @param integer $id ID del producto
     *
     * @return self
     */
    public static function product(int $id): self
    {
        return self::forResource('Producto', $id);
    }

    /**
     * Factory method: Usuario no encontrado
     *
     * @param integer $id ID del usuario
     *
     * @return self
     */
    public static function user(int $id): self
    {
        return self::forResource('Usuario', $id);
    }

    /**
     * Factory method: Animal no encontrado
     *
     * @param integer $id ID del animal
     *
     * @return self
     */
    public static function animal(int $id): self
    {
        return self::forResource('Animal', $id);
    }

    /**
     * Factory method: Pase no encontrado
     *
     * @param integer $id ID del pase
     *
     * @return self
     */
    public static function pass(int $id): self
    {
        return self::forResource('Pase', $id);
    }
}
