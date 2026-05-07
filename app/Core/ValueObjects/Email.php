<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

/**
 * Value Object para direcciones de email.
 *
 * Normaliza a minúsculas y recorta espacios.
 * Lanza ValidationException si el formato es inválido.
 */
final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = \strtolower(\trim($value));

        if ($normalized === '' || \filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(
                'Email inválido',
                ['email' => 'El email no tiene un formato válido']
            );
        }

        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
