<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Excepción lanzada cuando se recibe una cadena de fecha con formato inválido.
 */
final class DateMalformedStringException extends RuntimeException
{
    private int $httpCode = 400;

    /** Valor recibido que provocó el error */
    private mixed $received;

    /** Formato esperado (por ejemplo: 'Y-m-d' o 'H:i') */
    private ?string $expectedFormat;

    public function __construct(
        string $message = 'Fecha con formato inválido',
        mixed $received = null,
        ?string $expectedFormat = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->received = $received;
        $this->expectedFormat = $expectedFormat;
    }

    /**
     * Valor que provocó la excepción
     *
     * @return mixed
     */
    public function getReceived(): mixed
    {
        return $this->received;
    }

    /**
     * Formato de fecha esperado o hint
     */
    public function getExpectedFormat(): ?string
    {
        return $this->expectedFormat;
    }

    /**
     * Código HTTP asociado (400 Bad Request por defecto)
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Convierte la excepción a un array adecuado para JSON/API
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'expected_format' => $this->expectedFormat,
            'received' => $this->received,
            'code' => $this->httpCode,
        ];
    }

    /**
     * Factory: crear excepción a partir de un valor recibido y formato esperado
     */
    public static function fromValue(string $value, ?string $expectedFormat = null): self
    {
        $msg = 'Fecha con formato inválido';

        if ($expectedFormat !== null) {
            $msg = \sprintf('Fecha inválida: se esperaba el formato %s', $expectedFormat);
        }

        return new self($msg, $value, $expectedFormat);
    }
}
