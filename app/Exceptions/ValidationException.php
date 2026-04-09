<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Excepción para errores de validación de datos
 *
 * Se lanza cuando los datos de entrada no cumplen las reglas de validación.
 * Incluye un array de errores por campo para proporcionar feedback detallado.
 *
 * @package Komorebi\Exceptions
 */
final class ValidationException extends Exception
{
    private array $errors;
    private int $httpCode;

    /**
     * @param string         $message  Mensaje general del error
     * @param array          $errors   Array asociativo con errores por campo ['field' => 'error message']
     * @param integer        $httpCode Código HTTP (por defecto 422 Unprocessable Entity)
     * @param integer        $code     Código de error interno
     * @param Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $message = 'Error de validación',
        array $errors = [],
        int $httpCode = 422,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
        $this->httpCode = $httpCode;
    }

    /**
     * Obtiene los errores de validación
     *
     * @return array Array asociativo ['field' => 'error message']
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtiene el código HTTP asociado
     *
     * @return integer Código HTTP (422 por defecto)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Verifica si hay errores para un campo específico
     *
     * @param string $field Nombre del campo
     * @return boolean
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Obtiene el error de un campo específico
     *
     * @param string $field Nombre del campo
     * @return string|null Mensaje de error o null si no existe
     */
    public function getFieldError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Obtiene todos los mensajes de error como array plano
     *
     * @return array<string> Lista de mensajes de error
     */
    public function getErrorMessages(): array
    {
        return \array_values($this->errors);
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
            'errors' => $this->errors,
            'code' => $this->httpCode,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Factory Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Factory method: Crea excepción para un campo requerido
     *
     * @param string $field Nombre del campo
     * @return self
     */
    public static function required(string $field): self
    {
        return new self(
            'Campos requeridos faltantes',
            [$field => "El campo '$field' es requerido"]
        );
    }

    /**
     * Factory method: Crea excepción para formato inválido
     *
     * @param string $field          Nombre del campo
     * @param string $expectedFormat Formato esperado
     * @return self
     */
    public static function invalidFormat(string $field, string $expectedFormat): self
    {
        return new self(
            'Formato de datos inválido',
            [$field => "El campo '$field' debe tener formato: $expectedFormat"]
        );
    }

    /**
     * Factory method: Crea excepción para múltiples campos requeridos
     *
     * @param array<string> $fields Lista de campos requeridos
     * @return self
     */
    public static function multipleRequired(array $fields): self
    {
        $errors = [];
        foreach ($fields as $field) {
            $errors[$field] = "El campo '$field' es requerido";
        }

        return new self('Campos requeridos faltantes', $errors);
    }

    /**
     * Factory method: Crea excepción para valor fuera de rango
     *
     * @param string             $field Nombre del campo
     * @param integer|float      $min   Valor mínimo
     * @param integer|float|null $max   Valor máximo (opcional)
     * @return self
     */
    public static function outOfRange(string $field, int|float $min, int|float|null $max = null): self
    {
        $message = $max
            ? "El campo '$field' debe estar entre $min y $max"
            : "El campo '$field' debe ser al menos $min";

        return new self('Valor fuera de rango', [$field => $message]);
    }

    // Añadidos: métodos factory auxiliares usados por los controllers

    /**
     * Crea una ValidationException a partir de un array de errores
     *
     * @param array   $errors   Array asociativo ['field' => 'message']
     * @param string  $message  Mensaje general
     * @param integer $httpCode Código HTTP
     * @return self
     */
    public static function fromArray(array $errors, string $message = 'Error de validación', int $httpCode = 422): self
    {
        return new self($message, $errors, $httpCode);
    }

    /**
     * Crea una ValidationException solo con mensaje (sin errores por campo)
     *
     * @param string  $message  Mensaje
     * @param integer $httpCode Código HTTP
     * @return self
     */
    public static function withMessage(string $message, int $httpCode = 422): self
    {
        return new self($message, [], $httpCode);
    }
}
