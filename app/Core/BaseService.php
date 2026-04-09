<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;

/**
 * Clase base para Services.
 *
 * Proporciona helpers de validación y logging reutilizables.
 * No contiene PDO ni lógica transaccional — ver TransactionalService.
 */
abstract class BaseService
{
    // ─── Logging ──────────────────────────────────────────────────

    protected function logInfo(string $message, array $context = []): void
    {
        Logger::info('[' . static::class . '] ' . $message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Logger::error('[' . static::class . '] ' . $message, $context);
    }

    // ─── Validación ───────────────────────────────────────────────

    /**
     * @throws ValidationException
     */
    protected function assertNotBlank(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new ValidationException(
                'Campo requerido',
                [$field => "El campo {$field} es obligatorio"]
            );
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertMaxLength(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw new ValidationException(
                'Valor demasiado largo',
                [$field => "El campo {$field} no puede superar {$max} caracteres"]
            );
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertRange(int|float $value, int|float $min, int|float $max, string $field): void
    {
        if ($value < $min || $value > $max) {
            throw new ValidationException(
                'Valor fuera de rango',
                [$field => "El campo {$field} debe estar entre {$min} y {$max}"]
            );
        }
    }

    /**
     * @param array<mixed> $allowed
     * @throws ValidationException
     */
    protected function assertOneOf(mixed $value, array $allowed, string $field): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException(
                'Valor no permitido',
                [$field => "El campo {$field} debe ser uno de: " . implode(', ', array_map('strval', $allowed))]
            );
        }
    }
}
