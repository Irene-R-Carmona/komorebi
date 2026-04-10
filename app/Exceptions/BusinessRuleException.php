<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para violaciones de reglas de negocio
 *
 * Se lanza cuando una operación viola las reglas de negocio del sistema.
 * Ejemplos: reservar en fecha pasada, café sin reservas habilitadas,
 * capacidad insuficiente, etc.
 */
final class BusinessRuleException extends Exception
{
    private int $httpCode = 400;

    private ?string $ruleCode;

    private array $context;

    /**
     * @param string         $message  Mensaje descriptivo del error
     * @param string|null    $ruleCode Código identificador de la regla violada
     * @param array          $context  Contexto adicional (valores, límites, etc.)
     * @param integer        $code     Código de error interno
     * @param Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $message,
        ?string $ruleCode = null,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->ruleCode = $ruleCode;
        $this->context = $context;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (400)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Obtiene el código de la regla violada
     *
     * @return string|null
     */
    public function getRuleCode(): ?string
    {
        return $this->ruleCode;
    }

    /**
     * Obtiene el contexto del error
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
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
            'rule_code' => $this->ruleCode,
            'context' => $this->context,
            'code' => $this->httpCode,
        ];
    }

    /**
     * Crea una excepción con mensaje personalizado, código de regla y contexto
     *
     * @param string      $message
     * @param string|null $ruleCode
     * @param array       $context
     *
     * @return self
     */
    public static function withMessage(string $message, ?string $ruleCode = null, array $context = []): self
    {
        return new self($message, $ruleCode, $context);
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods - Reglas de Reservas
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Fecha/hora en el pasado
     *
     * @return self
     */
    public static function pastDate(): self
    {
        return new self(
            'No puedes reservar en una fecha/hora pasada',
            'past_date'
        );
    }

    /**
     * Factory method: Café no acepta reservas
     *
     * @return self
     */
    public static function cafeNotAcceptingReservations(): self
    {
        return new self(
            'Este café no acepta reservas actualmente',
            'cafe_reservations_disabled'
        );
    }

    /**
     * Factory method: Pase no disponible
     *
     * @return self
     */
    public static function passNotAvailable(): self
    {
        return new self(
            'Este pase no está disponible',
            'pass_not_available'
        );
    }

    /**
     * Factory method: Pase requiere más personas
     *
     * @param integer $required Número mínimo requerido
     *
     * @return self
     */
    public static function minimumGuestsRequired(int $required): self
    {
        return new self(
            "Este pase requiere al menos $required persona(s)",
            'minimum_guests_required',
            ['required' => $required]
        );
    }

    /**
     * Factory method: Pase excede máximo de personas
     *
     * @param integer $max Número máximo permitido
     *
     * @return self
     */
    public static function maximumGuestsExceeded(int $max): self
    {
        return new self(
            "Este pase permite máximo $max persona(s)",
            'maximum_guests_exceeded',
            ['max' => $max]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods - Reglas de Productos
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Producto no disponible
     *
     * @return self
     */
    public static function productNotAvailable(): self
    {
        return new self(
            'Este producto no está disponible',
            'product_not_available'
        );
    }

    /**
     * Factory method: Operación no permitida en estado actual
     *
     * @param string $operation    Operación intentada
     * @param string $currentState Estado actual
     *
     * @return self
     */
    public static function invalidStateForOperation(string $operation, string $currentState): self
    {
        return new self(
            "No se puede realizar '$operation' en estado '$currentState'",
            'invalid_state_for_operation',
            ['operation' => $operation, 'current_state' => $currentState]
        );
    }
}
